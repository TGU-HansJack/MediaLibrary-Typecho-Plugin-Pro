/**
 * 分片上传管理器
 * 支持大文件分片上传，断点续传，失败重试
 */
var ChunkedUploader = (function() {
    'use strict';

    // 默认配置
    var defaults = {
        chunkSize: 2 * 1024 * 1024, // 2MB
        maxRetries: 3,              // 最大重试次数
        retryDelay: 1000,           // 重试延迟（毫秒）
        concurrency: 3,             // 并发上传数
        threshold: 5 * 1024 * 1024, // 超过此大小才使用分片上传 (5MB)
        apiUrl: ''
    };

    /**
     * 构造函数
     * @param {Object} options 配置选项
     */
    function ChunkedUploader(options) {
        this.options = Object.assign({}, defaults, options);
        this.uploadQueue = [];
        this.activeUploads = 0;
    }

    /**
     * 检查文件是否需要分片上传
     * @param {File} file 文件对象
     * @return {boolean}
     */
    ChunkedUploader.prototype.needsChunking = function(file) {
        return file.size > this.options.threshold;
    };

    /**
     * 计算文件 MD5（用于校验，可选）
     * @param {File} file 文件对象
     * @return {Promise<string>}
     */
    ChunkedUploader.prototype.calculateMD5 = function(file) {
        // 简化实现：仅用文件名和大小生成唯一标识
        // 完整实现可以使用 SparkMD5 库计算实际 MD5
        return Promise.resolve('');
    };

    /**
     * 初始化分片上传
     * @param {File} file 文件对象
     * @param {string} storage 存储类型
     * @return {Promise<Object>}
     */
    ChunkedUploader.prototype.initUpload = function(file, storage) {
        var self = this;

        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', self.options.apiUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.timeout = 60000; // 1分钟超时

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                resolve(response);
                            } else {
                                reject(new Error(response.message || '初始化上传失败'));
                            }
                        } catch (e) {
                            console.error('initUpload 解析响应失败:', xhr.responseText);
                            reject(new Error('解析响应失败'));
                        }
                    } else if (xhr.status === 0) {
                        reject(new Error('请求被取消或超时'));
                    } else {
                        reject(new Error('HTTP 错误: ' + xhr.status));
                    }
                }
            };

            xhr.ontimeout = function() {
                reject(new Error('初始化请求超时'));
            };

            xhr.onerror = function() {
                reject(new Error('网络错误'));
            };

            var params = [
                'action=chunked_init',
                'filename=' + encodeURIComponent(file.name),
                'filesize=' + file.size,
                'chunkSize=' + self.options.chunkSize,
                'storage=' + encodeURIComponent(storage)
            ].join('&');

            xhr.send(params);
        });
    };

    /**
     * 上传单个分片
     * @param {Object} params 上传参数
     * @return {Promise<Object>}
     */
    ChunkedUploader.prototype.uploadChunk = function(params) {
        var self = this;

        return new Promise(function(resolve, reject) {
            var formData = new FormData();
            formData.append('action', 'chunked_upload');
            formData.append('uploadId', params.uploadId);
            formData.append('chunkIndex', params.chunkIndex);
            formData.append('chunk', params.chunk);

            var xhr = new XMLHttpRequest();
            xhr.open('POST', self.options.apiUrl, true);
            xhr.timeout = 120000; // 2分钟超时（单个分片）

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                resolve(response);
                            } else {
                                reject(new Error(response.message || '上传分片失败'));
                            }
                        } catch (e) {
                            console.error('uploadChunk 解析响应失败:', xhr.responseText);
                            reject(new Error('解析响应失败'));
                        }
                    } else if (xhr.status === 0) {
                        reject(new Error('分片上传被取消或超时'));
                    } else {
                        reject(new Error('HTTP 错误: ' + xhr.status));
                    }
                }
            };

            xhr.ontimeout = function() {
                reject(new Error('分片上传超时'));
            };

            xhr.onerror = function() {
                reject(new Error('网络错误'));
            };

            xhr.send(formData);
        });
    };

    /**
     * 带重试的上传分片
     * @param {Object} params 上传参数
     * @param {number} retries 剩余重试次数
     * @return {Promise<Object>}
     */
    ChunkedUploader.prototype.uploadChunkWithRetry = function(params, retries) {
        var self = this;
        retries = typeof retries === 'number' ? retries : self.options.maxRetries;

        return self.uploadChunk(params).catch(function(error) {
            if (retries > 0) {
                return new Promise(function(resolve) {
                    setTimeout(function() {
                        resolve(self.uploadChunkWithRetry(params, retries - 1));
                    }, self.options.retryDelay);
                });
            }
            throw error;
        });
    };

    /**
     * 完成分片上传
     * @param {string} uploadId 上传ID
     * @param {string} filename 文件名（用于状态检查）
     * @return {Promise<Object>}
     */
    ChunkedUploader.prototype.completeUpload = function(uploadId, filename) {
        var self = this;

        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', self.options.apiUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            // 设置较长的超时时间（5分钟），因为合并大文件可能需要较长时间
            xhr.timeout = 300000;

            var handleError = function(errorMsg) {
                // 当请求失败时，检查上传是否实际已完成
                console.log('completeUpload 请求失败，检查上传状态...');
                self.checkUploadStatus(uploadId, filename).then(function(statusResult) {
                    if (statusResult.completed && statusResult.data) {
                        console.log('上传状态检查：已完成');
                        resolve(statusResult);
                    } else if (statusResult.processing) {
                        // 正在处理中，等待后重试检查
                        console.log('上传状态检查：处理中，等待重试...');
                        setTimeout(function() {
                            self.checkUploadStatus(uploadId, filename).then(function(retryResult) {
                                if (retryResult.completed && retryResult.data) {
                                    resolve(retryResult);
                                } else {
                                    reject(new Error(errorMsg));
                                }
                            }).catch(function() {
                                reject(new Error(errorMsg));
                            });
                        }, 3000);
                    } else {
                        reject(new Error(errorMsg));
                    }
                }).catch(function() {
                    reject(new Error(errorMsg));
                });
            };

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                resolve(response);
                            } else {
                                reject(new Error(response.message || '完成上传失败'));
                            }
                        } catch (e) {
                            console.error('解析响应失败:', xhr.responseText);
                            handleError('解析响应失败');
                        }
                    } else if (xhr.status === 0) {
                        // 状态 0 可能是超时或请求被取消，检查实际状态
                        handleError('请求超时或被取消');
                    } else {
                        reject(new Error('HTTP 错误: ' + xhr.status));
                    }
                }
            };

            xhr.ontimeout = function() {
                handleError('请求超时');
            };

            xhr.onerror = function() {
                handleError('网络错误');
            };

            var params = 'action=chunked_complete&uploadId=' + encodeURIComponent(uploadId);
            xhr.send(params);
        });
    };

    /**
     * 检查上传状态
     * @param {string} uploadId 上传ID
     * @param {string} filename 文件名
     * @return {Promise<Object>}
     */
    ChunkedUploader.prototype.checkUploadStatus = function(uploadId, filename) {
        var self = this;

        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', self.options.apiUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.timeout = 30000;

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (e) {
                            reject(new Error('解析响应失败'));
                        }
                    } else {
                        reject(new Error('HTTP 错误: ' + xhr.status));
                    }
                }
            };

            xhr.onerror = function() {
                reject(new Error('网络错误'));
            };

            var params = 'action=chunked_status&uploadId=' + encodeURIComponent(uploadId) +
                '&filename=' + encodeURIComponent(filename);
            xhr.send(params);
        });
    };

    /**
     * 取消上传
     * @param {string} uploadId 上传ID
     * @return {Promise<Object>}
     */
    ChunkedUploader.prototype.cancelUpload = function(uploadId) {
        var self = this;

        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', self.options.apiUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');

            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            var response = JSON.parse(xhr.responseText);
                            resolve(response);
                        } catch (e) {
                            resolve({ success: true });
                        }
                    } else {
                        resolve({ success: true });
                    }
                }
            };

            xhr.onerror = function() {
                resolve({ success: true });
            };

            var params = 'action=chunked_cancel&uploadId=' + encodeURIComponent(uploadId);
            xhr.send(params);
        });
    };

    /**
     * 上传文件
     * @param {File} file 文件对象
     * @param {string} storage 存储类型
     * @param {Object} callbacks 回调函数
     * @return {Promise<Object>}
     */
    ChunkedUploader.prototype.upload = function(file, storage, callbacks) {
        var self = this;
        callbacks = callbacks || {};

        var onProgress = callbacks.onProgress || function() {};
        var onChunkComplete = callbacks.onChunkComplete || function() {};
        var onError = callbacks.onError || function() {};
        var onCancel = callbacks.onCancel || function() {};

        // 创建取消令牌
        var cancelled = false;
        var uploadId = null;

        var cancelToken = {
            cancel: function() {
                cancelled = true;
                if (uploadId) {
                    self.cancelUpload(uploadId).then(function() {
                        onCancel();
                    });
                } else {
                    onCancel();
                }
            }
        };

        var uploadPromise = self.initUpload(file, storage).then(function(initResult) {
            if (cancelled) {
                throw new Error('上传已取消');
            }

            uploadId = initResult.uploadId;
            var totalChunks = initResult.totalChunks;
            var uploadedChunks = initResult.uploadedChunks || [];
            var chunkSize = initResult.chunkSize;

            // 构建待上传的分片列表（排除已上传的）
            var pendingChunks = [];
            for (var i = 0; i < totalChunks; i++) {
                if (uploadedChunks.indexOf(i) === -1) {
                    pendingChunks.push(i);
                }
            }

            // 计算初始进度
            var completedChunks = uploadedChunks.length;
            onProgress({
                loaded: completedChunks * chunkSize,
                total: file.size,
                percent: Math.round(completedChunks / totalChunks * 100),
                uploadedChunks: completedChunks,
                totalChunks: totalChunks
            });

            // 顺序上传所有分片
            var uploadNextChunk = function(index) {
                if (cancelled) {
                    throw new Error('上传已取消');
                }

                if (index >= pendingChunks.length) {
                    // 所有分片上传完成
                    return Promise.resolve();
                }

                var chunkIndex = pendingChunks[index];
                var start = chunkIndex * chunkSize;
                var end = Math.min(start + chunkSize, file.size);
                var chunk = file.slice(start, end);

                return self.uploadChunkWithRetry({
                    uploadId: uploadId,
                    chunkIndex: chunkIndex,
                    chunk: chunk
                }).then(function(result) {
                    completedChunks++;

                    onProgress({
                        loaded: Math.min(completedChunks * chunkSize, file.size),
                        total: file.size,
                        percent: Math.round(completedChunks / totalChunks * 100),
                        uploadedChunks: completedChunks,
                        totalChunks: totalChunks
                    });

                    onChunkComplete({
                        chunkIndex: chunkIndex,
                        uploadedChunks: result.uploadedChunks,
                        progress: result.progress
                    });

                    return uploadNextChunk(index + 1);
                });
            };

            // 使用并发上传提高效率
            var concurrency = Math.min(self.options.concurrency, pendingChunks.length);
            var uploadPromises = [];

            var chunkQueues = [];
            for (var c = 0; c < concurrency; c++) {
                chunkQueues.push([]);
            }

            for (var p = 0; p < pendingChunks.length; p++) {
                chunkQueues[p % concurrency].push(pendingChunks[p]);
            }

            var uploadChunkQueue = function(queue) {
                if (queue.length === 0) {
                    return Promise.resolve();
                }

                var chunkIndex = queue.shift();

                if (cancelled) {
                    return Promise.reject(new Error('上传已取消'));
                }

                var start = chunkIndex * chunkSize;
                var end = Math.min(start + chunkSize, file.size);
                var chunk = file.slice(start, end);

                return self.uploadChunkWithRetry({
                    uploadId: uploadId,
                    chunkIndex: chunkIndex,
                    chunk: chunk
                }).then(function(result) {
                    completedChunks++;

                    onProgress({
                        loaded: Math.min(completedChunks * chunkSize, file.size),
                        total: file.size,
                        percent: Math.round(completedChunks / totalChunks * 100),
                        uploadedChunks: completedChunks,
                        totalChunks: totalChunks
                    });

                    onChunkComplete({
                        chunkIndex: chunkIndex,
                        uploadedChunks: result.uploadedChunks,
                        progress: result.progress
                    });

                    return uploadChunkQueue(queue);
                });
            };

            for (var q = 0; q < concurrency; q++) {
                uploadPromises.push(uploadChunkQueue(chunkQueues[q]));
            }

            return Promise.all(uploadPromises);

        }).then(function() {
            if (cancelled) {
                throw new Error('上传已取消');
            }

            // 所有分片上传完成，通知服务器合并
            return self.completeUpload(uploadId, file.name);

        }).then(function(result) {
            return result;

        }).catch(function(error) {
            onError(error);
            throw error;
        });

        // 返回带取消功能的 Promise
        uploadPromise.cancel = cancelToken.cancel;

        return uploadPromise;
    };

    return ChunkedUploader;
})();

// 导出到全局
window.ChunkedUploader = ChunkedUploader;
