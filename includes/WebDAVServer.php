<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

/**
 * WebDAV 服务器基础类（提取自 picodav）
 * 这个文件包含了 WebDAV 协议的核心实现
 */

namespace MediaLibrary\WebDAV;

class Exception extends \RuntimeException {}

abstract class AbstractStorage
{
    abstract public function get(string $uri): ?array;
    abstract public function exists(string $uri): bool;
    abstract public function propfind(string $uri, ?array $requested_properties, int $depth): ?array;

    public function proppatch(string $uri, array $properties): array
    {
        return [];
    }

    abstract public function put(string $uri, $pointer, ?string $hash_algo, ?string $hash): bool;
    abstract public function delete(string $uri): void;
    abstract public function copy(string $uri, string $destination): bool;
    abstract public function move(string $uri, string $destination): bool;
    abstract public function mkcol(string $uri): void;
    abstract public function list(string $uri, ?array $properties): iterable;
    abstract public function touch(string $uri, \DateTimeInterface $timestamp): bool;

    public function lock(string $uri, string $token, string $scope): void {}
    public function unlock(string $uri, string $token): void {}
    public function getLock(string $uri, ?string $token = null): ?string { return null; }
}

/**
 * 简化版的 WebDAV Server 实现
 */
class Server
{
    const BASIC_PROPERTIES = [
        'DAV::resourcetype',
        'DAV::getcontenttype',
        'DAV::getlastmodified',
        'DAV::getcontentlength',
        'DAV::displayname',
    ];

    const MODIFICATION_TIME_PROPERTIES = [
        'DAV::lastmodified',
        'DAV::creationdate',
        'DAV::getlastmodified',
    ];

    const PROP_DIGEST_MD5 = 'urn:karadav:digest_md5';
    const EMPTY_PROP_VALUE = 'DAV::empty';
    const SHARED_LOCK = 'shared';
    const EXCLUSIVE_LOCK = 'exclusive';

    protected bool $enable_gzip = true;
    protected string $base_uri;
    public string $original_uri;
    public string $prefix = '';
    protected AbstractStorage $storage;

    public function setStorage(AbstractStorage $storage)
    {
        $this->storage = $storage;
    }

    public function getStorage(): AbstractStorage
    {
        return $this->storage;
    }

    public function setBaseURI(string $uri): void
    {
        $this->base_uri = '/' . ltrim($uri, '/');
        $this->base_uri = rtrim($this->base_uri, '/') . '/';
    }

    protected function extendExecutionTime(): void
    {
        if (false === strpos(@ini_get('disable_functions'), 'set_time_limit')) {
            @set_time_limit(3600);
        }
        @ini_set('max_execution_time', '3600');
        @ini_set('max_input_time', '3600');
    }

    protected function _prefix(string $uri): string
    {
        if (!$this->prefix) {
            return $uri;
        }
        return rtrim(rtrim($this->prefix, '/') . '/' . ltrim($uri, '/'), '/');
    }

    public function http_options(): void
    {
        http_response_code(200);
        $methods = 'GET HEAD PUT DELETE COPY MOVE PROPFIND MKCOL';

        $this->dav_header();
        header('Allow: ' . $methods);
        header('Content-length: 0');
        header('Accept-Ranges: bytes');
        header('MS-Author-Via: DAV');
    }

    protected function dav_header()
    {
        header('DAV: 1, 2');
    }

    public function http_propfind(string $uri): ?string
    {
        $depth = isset($_SERVER['HTTP_DEPTH']) && empty($_SERVER['HTTP_DEPTH']) ? 0 : 1;
        $uri = $this->_prefix($uri);
        $body = file_get_contents('php://input');

        $requested = $this->extractRequestedProperties($body);
        $requested_keys = $requested ? array_keys($requested) : null;

        $properties = $this->storage->propfind($uri, $requested_keys, $depth);

        if (null === $properties) {
            throw new Exception('This does not exist', 404);
        }

        if (isset($properties['DAV::getlastmodified'])) {
            foreach (self::MODIFICATION_TIME_PROPERTIES as $name) {
                $properties[$name] = $properties['DAV::getlastmodified'];
            }
        }

        $items = [$uri => $properties];

        if ($depth) {
            foreach ($this->storage->list($uri, $requested) as $file => $props) {
                $path = trim($uri . '/' . $file, '/');
                $properties = $props ?? $this->storage->propfind($path, $requested_keys, 0);

                if (!$properties) {
                    continue;
                }

                $items[$path] = $properties;
            }
        }

        header('HTTP/1.1 207 Multi-Status', true);
        $this->dav_header();
        header('Content-Type: application/xml; charset=utf-8');

        return $this->generateMultistatusResponse($items, $requested);
    }

    protected function extractRequestedProperties(string $body): ?array
    {
        if (!preg_match('!<(?:\w+:)?propfind!', $body)) {
            return null;
        }

        $ns = [];
        $dav_ns = null;
        $default_ns = null;

        if (preg_match('/<propfind[^>]+xmlns="DAV:"/', $body)) {
            $default_ns = 'DAV:';
        }

        preg_match_all('!xmlns:(\w+)\s*=\s*"([^"]+)"!', $body, $match, PREG_SET_ORDER);

        foreach ($match as $found) {
            $ns[$found[2]] = $found[1];
        }

        if (isset($ns['DAV:'])) {
            $dav_ns = $ns['DAV:'] . ':';
        }

        $regexp = '/<(' . $dav_ns . 'prop(?!find))[^>]*?>(.*?)<\/\1\s*>/s';
        if (!preg_match($regexp, $body, $match)) {
            return null;
        }

        preg_match_all('!<([\w-]+)[^>]*xmlns="([^"]*)"|<(?:([\w-]+):)?([\w-]+)!', $match[2], $match, PREG_SET_ORDER);

        $properties = [];

        foreach ($match as $found) {
            if (isset($found[4])) {
                $url = array_search($found[3], $ns) ?: $default_ns;
                $name = $found[4];
            } else {
                $url = $found[2];
                $name = $found[1];
            }

            $properties[$url . ':' . $name] = [
                'name' => $name,
                'ns_alias' => $found[3] ?? null,
                'ns_url' => $url,
            ];
        }

        return $properties;
    }

    protected function generateMultistatusResponse(array $items, ?array $requested): string
    {
        $root_namespaces = [
            'DAV:' => 'd',
            'urn:uuid:c2f41010-65b3-11d1-a29f-00aa00c14882/' => 'ns0',
        ];

        $i = 0;
        $requested ??= [];

        foreach ($requested as $prop) {
            if ($prop['ns_url'] == 'DAV:' || !$prop['ns_url']) {
                continue;
            }

            if (!array_key_exists($prop['ns_url'], $root_namespaces)) {
                $root_namespaces[$prop['ns_url']] = $prop['ns_alias'] ?: 'rns' . $i++;
            }
        }

        foreach ($items as $properties) {
            foreach ($properties as $name => $value) {
                $pos = strrpos($name, ':');
                $ns = substr($name, 0, strrpos($name, ':'));

                if (!$ns) {
                    continue;
                }

                if (!array_key_exists($ns, $root_namespaces)) {
                    $root_namespaces[$ns] = 'rns' . $i++;
                }
            }
        }

        $out = '<?xml version="1.0" encoding="utf-8"?>';
        $out .= '<d:multistatus';

        foreach ($root_namespaces as $url => $alias) {
            $out .= sprintf(' xmlns:%s="%s"', $alias, $url);
        }

        $out .= '>';

        foreach ($items as $uri => $item) {
            $e = '<d:response>';

            if ($this->prefix) {
                $uri = substr($uri, strlen($this->prefix));
            }

            $uri = trim(rtrim($this->base_uri, '/') . '/' . ltrim($uri, '/'), '/');
            $path = '/' . str_replace('%2F', '/', rawurlencode($uri));

            if (($item['DAV::resourcetype'] ?? null) == 'collection' && $path != '/') {
                $path .= '/';
            }

            $e .= sprintf('<d:href>%s</d:href>', htmlspecialchars($path, ENT_XML1));
            $e .= '<d:propstat><d:prop>';

            foreach ($item as $name => $value) {
                if (null === $value) {
                    continue;
                }

                $pos = strrpos($name, ':');
                $ns = substr($name, 0, strrpos($name, ':'));
                $tag_name = substr($name, strrpos($name, ':') + 1);

                $alias = $root_namespaces[$ns] ?? null;
                $attributes = '';

                if ($name == 'DAV::resourcetype' && $value == 'collection') {
                    $value = '<d:collection />';
                } elseif ($name == 'DAV::getetag' && strlen($value) && $value[0] != '"') {
                    $value = '"' . $value . '"';
                } elseif ($value instanceof \DateTimeInterface) {
                    $value = clone $value;
                    $value->setTimezone(new \DateTimeZone('GMT'));
                    $value = $value->format(DATE_RFC7231);
                } elseif (is_array($value)) {
                    $attributes = $value['attributes'] ?? '';
                    $value = $value['xml'] ?? null;
                } else {
                    $value = htmlspecialchars($value, ENT_XML1);
                }

                if (!$ns) {
                    $attributes .= ' xmlns=""';
                } else {
                    $tag_name = $alias . ':' . $tag_name;
                }

                if (null === $value || self::EMPTY_PROP_VALUE === $value) {
                    $e .= sprintf('<%s%s />', $tag_name, $attributes ? ' ' . $attributes : '');
                } else {
                    $e .= sprintf('<%s%s>%s</%1$s>', $tag_name, $attributes ? ' ' . $attributes : '', $value);
                }
            }

            $e .= '</d:prop><d:status>HTTP/1.1 200 OK</d:status></d:propstat>' . "\n";
            $e .= '</d:response>' . "\n";
            $out .= $e;
        }

        $out .= '</d:multistatus>';

        return $out;
    }

    public function http_get(string $uri): ?string
    {
        $props = [];
        $this->http_head($uri, $props);

        $uri = $this->_prefix($uri);

        $is_collection = !empty($props['DAV::resourcetype']) && $props['DAV::resourcetype'] == 'collection';

        if ($is_collection) {
            $list = $this->storage->list($uri, self::BASIC_PROPERTIES);

            if (!isset($_SERVER['HTTP_ACCEPT']) || false === strpos($_SERVER['HTTP_ACCEPT'], 'html')) {
                $list = is_array($list) ? $list : iterator_to_array($list);

                if (!count($list)) {
                    return "Nothing in this collection\n";
                }

                return implode("\n", array_keys($list));
            }

            header('Content-Type: text/html; charset=utf-8', true);
            return $this->html_directory($uri, $list);
        }

        $file = $this->storage->get($uri);

        if (!$file) {
            throw new Exception('File Not Found', 404);
        }

        if (!empty($file['stop'])) {
            return null;
        }

        if (!isset($file['content']) && !isset($file['resource']) && !isset($file['path'])) {
            throw new \RuntimeException('Invalid file array returned by ::get()');
        }

        $this->extendExecutionTime();

        if (isset($file['content'])) {
            header('Content-Length: ' . strlen($file['content']), true);
            echo $file['content'];
            return null;
        }

        if (isset($file['path'])) {
            $file['resource'] = fopen($file['path'], 'rb');
        }

        $seek = fseek($file['resource'], 0, SEEK_END);

        if ($seek === 0) {
            $length = ftell($file['resource']);
            fseek($file['resource'], 0, SEEK_SET);
            header('Content-Length: ' . $length, true);
        }

        $block_size = 8192 * 4;

        while (!feof($file['resource'])) {
            echo fread($file['resource'], $block_size);
            flush();
        }

        fclose($file['resource']);

        return null;
    }

    public function http_head(string $uri, array &$props = []): ?string
    {
        $uri = $this->_prefix($uri);

        $requested_props = self::BASIC_PROPERTIES;
        $requested_props[] = 'DAV::getetag';

        $props = $this->storage->propfind($uri, $requested_props, 0);

        if (!$props) {
            throw new Exception('Resource Not Found', 404);
        }

        http_response_code(200);

        if (isset($props['DAV::getlastmodified'])
            && $props['DAV::getlastmodified'] instanceof \DateTimeInterface) {
            header(sprintf('Last-Modified: %s', $props['DAV::getlastmodified']->format(\DATE_RFC7231)));
        }

        if (!empty($props['DAV::getetag'])) {
            $value = $props['DAV::getetag'];

            if (substr($value, 0, 1) != '"') {
                $value = '"' . $value . '"';
            }

            header(sprintf('ETag: %s', $value));
        }

        if (empty($props['DAV::resourcetype']) || $props['DAV::resourcetype'] != 'collection') {
            if (!empty($props['DAV::getcontenttype'])) {
                header(sprintf('Content-Type: %s', $props['DAV::getcontenttype']));
            }

            if (!empty($props['DAV::getcontentlength'])) {
                header(sprintf('Content-Length: %d', $props['DAV::getcontentlength']));
                header('Accept-Ranges: bytes');
            }
        }

        return null;
    }

    protected function html_directory(string $uri, iterable $list): ?string
    {
        if (substr($this->original_uri, -1) != '/') {
            http_response_code(301);
            header(sprintf('Location: /%s/', trim($this->base_uri . $uri, '/')), true);
            return null;
        }

        $out = sprintf('<!DOCTYPE html><html><head><meta charset="utf-8"><title>%s</title></head><body>', htmlspecialchars($uri ?: 'Files'));
        $out .= sprintf('<h1>%s</h1><ul>', htmlspecialchars($uri ? $uri : 'Files'));

        if (trim($uri)) {
            $out .= '<li><a href="../"><strong>[Back]</strong></a></li>';
        }

        foreach ($list as $file => $props) {
            if (null === $props) {
                $props = $this->storage->propfind(trim($uri . '/' . $file, '/'), self::BASIC_PROPERTIES, 0);
            }

            $collection = !empty($props['DAV::resourcetype']) && $props['DAV::resourcetype'] == 'collection';

            if ($collection) {
                $out .= sprintf('<li><a href="%s/"><strong>%s/</strong></a></li>', rawurlencode($file), htmlspecialchars($file));
            } else {
                $size = $props['DAV::getcontentlength'] ?? 0;
                $out .= sprintf('<li><a href="%s">%s</a> (%s)</li>',
                    rawurlencode($file),
                    htmlspecialchars($file),
                    $this->formatBytes($size)
                );
            }
        }

        $out .= '</ul></body></html>';

        return $out;
    }

    protected function formatBytes($bytes)
    {
        if ($bytes >= 1024 * 1024 * 1024) {
            return sprintf('%.2f GB', $bytes / (1024 * 1024 * 1024));
        } elseif ($bytes >= 1024 * 1024) {
            return sprintf('%.2f MB', $bytes / (1024 * 1024));
        } elseif ($bytes >= 1024) {
            return sprintf('%.2f KB', $bytes / 1024);
        } else {
            return $bytes . ' B';
        }
    }

    public function http_put(string $uri): ?string
    {
        $uri = $this->_prefix($uri);

        $hash = null;
        $hash_algo = null;

        if (!empty($_SERVER['HTTP_CONTENT_MD5'])) {
            $hash = bin2hex(base64_decode($_SERVER['HTTP_CONTENT_MD5']));
            $hash_algo = 'MD5';
        }

        $this->extendExecutionTime();

        $stream = fopen('php://input', 'r');
        $created = $this->storage->put($uri, $stream, $hash_algo, $hash);

        http_response_code($created ? 201 : 204);
        return null;
    }

    public function http_delete(string $uri): ?string
    {
        if (isset($_SERVER['HTTP_DEPTH']) && $_SERVER['HTTP_DEPTH'] != 'infinity') {
            throw new Exception('We can only delete to infinity', 400);
        }

        $uri = $this->_prefix($uri);
        $this->storage->delete($uri);

        http_response_code(204);
        header('Content-Length: 0', true);
        return null;
    }

    public function http_mkcol(string $uri): ?string
    {
        if (!empty($_SERVER['CONTENT_LENGTH'])) {
            throw new Exception('Unsupported body for MKCOL', 415);
        }

        $uri = $this->_prefix($uri);
        $this->storage->mkcol($uri);

        http_response_code(201);
        return null;
    }

    public function http_copy(string $uri): ?string
    {
        return $this->_http_copymove($uri, 'copy');
    }

    public function http_move(string $uri): ?string
    {
        return $this->_http_copymove($uri, 'move');
    }

    protected function _http_copymove(string $uri, string $method): ?string
    {
        $uri = $this->_prefix($uri);

        $destination = $_SERVER['HTTP_DESTINATION'] ?? null;

        if (!$destination) {
            throw new Exception('Destination not supplied', 400);
        }

        $destination = $this->getURI($destination);

        if (trim($destination, '/') == trim($uri, '/')) {
            throw new Exception('Cannot move file to itself', 403);
        }

        $overwrite = ($_SERVER['HTTP_OVERWRITE'] ?? null) == 'T';

        if (!$overwrite && $this->storage->exists($destination)) {
            throw new Exception('File already exists and overwriting is disabled', 412);
        }

        $overwritten = $this->storage->$method($uri, $destination);

        http_response_code($overwritten ? 204 : 201);
        return null;
    }

    protected function getURI(string $source): string
    {
        $uri = parse_url($source, PHP_URL_PATH);
        $uri = rawurldecode($uri);
        $uri = trim($uri, '/');
        $uri = '/' . $uri;

        if ($uri . '/' === $this->base_uri) {
            $uri .= '/';
        }

        if (strpos($uri, $this->base_uri) !== 0) {
            throw new Exception(sprintf('Invalid URI, "%s" is outside of scope "%s"', $uri, $this->base_uri), 400);
        }

        $uri = preg_replace('!/{2,}!', '/', $uri);

        if (false !== strpos($uri, '..')) {
            throw new Exception(sprintf('Invalid URI: "%s"', $uri), 403);
        }

        $uri = substr($uri, strlen($this->base_uri));
        $uri = $this->_prefix($uri);
        return $uri;
    }

    public function log(string $message, ...$params)
    {
        if (PHP_SAPI == 'cli-server') {
            file_put_contents('php://stderr', vsprintf($message, $params) . "\n");
        }
    }

    public function route(?string $uri = null): bool
    {
        if (null === $uri) {
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
        }

        $uri = '/' . ltrim($uri, '/');
        $this->original_uri = $uri;

        if ($uri . '/' == $this->base_uri) {
            $uri .= '/';
        }

        if (0 === strpos($uri, $this->base_uri)) {
            $uri = substr($uri, strlen($this->base_uri));
        } else {
            return false;
        }

        $method = $_SERVER['REQUEST_METHOD'] ?? null;

        header_remove('Expires');
        header_remove('Pragma');
        header_remove('Cache-Control');

        if ($method == 'OPTIONS') {
            $this->http_options();
            return true;
        }

        $uri = rawurldecode($uri);
        $uri = trim($uri, '/');
        $uri = preg_replace('!/{2,}!', '/', $uri);

        try {
            if (false !== strpos($uri, '..')) {
                throw new Exception(sprintf('Invalid URI: "%s"', $uri), 403);
            }

            $method = 'http_' . strtolower($method);

            if (!method_exists($this, $method)) {
                throw new Exception('Invalid request method', 405);
            }

            $out = $this->$method($uri);

            if (null !== $out) {
                echo $out;
            }
        } catch (Exception $e) {
            $this->error($e);
        }

        return true;
    }

    function error(Exception $e)
    {
        if ($e->getCode() == 423) {
            header('HTTP/1.1 423 Locked');
        } else {
            http_response_code($e->getCode());
        }

        header('Content-Type: application/xml; charset=utf-8', true);

        printf('<?xml version="1.0" encoding="utf-8"?><d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns"><s:message>%s</s:message></d:error>',
            htmlspecialchars($e->getMessage(), ENT_XML1));
    }
}
