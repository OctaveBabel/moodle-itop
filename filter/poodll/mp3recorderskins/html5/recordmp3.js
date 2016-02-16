
/* CONTROLLER */

var audio_context;
var recorder;
var localStream;
function startUserMedia(stream) {
    // prevent firefox garbage collector to remove the stream
    localStream = stream;

    var input = audio_context.createMediaStreamSource(stream);
    console.log('Media stream created.');
    console.log("input sample rate " + input.context.sampleRate);
    //input.connect(audio_context.destination);
    console.log('Input connected to audio context destination.');
    recorder = new Recorder(input, {
        numChannels: 1
    });
    console.log('Recorder initialised.');
}
function startRecording(button) {
    recorder && recorder.record();
    button.disabled = true;
    button.nextElementSibling.disabled = false;
    console.log('Recording...');
    // todo : add a chrono for current record
}
function stopRecording(button) {
    recorder && recorder.stop();
    button.disabled = true;
    button.previousElementSibling.disabled = false;
    console.log('Stopped recording.');
    recorder && recorder.exportWAV(function (blob) { });
    recorder.clear();
}
function getParameterByName(name) {
    name = name.replace(/[\[]/, "\\[").replace(/[\]]/, "\\]");
    var regex = new RegExp("[\\?&]" + name + "=([^&#]*)"),
        results = regex.exec(location.search);
    return results === null ? "" : decodeURIComponent(results[1].replace(/\+/g, " "));
}
window.onload = function init() {
    try {
        // webkit shim
        window.AudioContext = window.AudioContext || window.webkitAudioContext;
        navigator.getUserMedia = (navigator.getUserMedia ||
                       navigator.webkitGetUserMedia ||
                       navigator.mozGetUserMedia ||
                       navigator.msGetUserMedia);
        window.URL = window.URL || window.webkitURL;
        audio_context = new AudioContext;
        console.log('Audio context set up.');
        console.log('navigator.getUserMedia ' + (navigator.getUserMedia ? 'disponible.' : 'non présent !'));
    } catch (e) {
        alert('Pas de support API Web Audio dans ce navigateur !');
    }
    navigator.getUserMedia({ audio: true }, startUserMedia, function (e) {
        console.log('Pas d\'entrée audio : ' + e);
    });
};

/* WORKER */

(function (window) {

    var WORKER_PATH = 'js/recorderWorker.js';
    var encoderWorker = new Worker('js/mp3Worker.js');

    var Recorder = function (source, cfg) {
        var config = cfg || {};
        var bufferLen = config.bufferLen || 4096;
        var numChannels = config.numChannels || 2;
        this.context = source.context;
        this.node = (this.context.createScriptProcessor ||
                     this.context.createJavaScriptNode).call(this.context,
                     bufferLen, numChannels, numChannels);
        var worker = new Worker(config.workerPath || WORKER_PATH);
        worker.postMessage({
            command: 'init',
            config: {
                sampleRate: this.context.sampleRate,
                numChannels: numChannels
            }
        });
        var recording = false,
          currCallback;

        this.node.onaudioprocess = function (e) {
            if (!recording) return;
            var buffer = [];
            for (var channel = 0; channel < numChannels; channel++) {
                buffer.push(e.inputBuffer.getChannelData(channel));
            }
            worker.postMessage({
                command: 'record',
                buffer: buffer
            });
        }

        this.configure = function (cfg) {
            for (var prop in cfg) {
                if (cfg.hasOwnProperty(prop)) {
                    config[prop] = cfg[prop];
                }
            }
        }

        this.record = function () {
            recording = true;
        }

        this.stop = function () {
            recording = false;
        }

        this.clear = function () {
            worker.postMessage({ command: 'clear' });
        }

        this.getBuffer = function (cb) {
            currCallback = cb || config.callback;
            worker.postMessage({ command: 'getBuffer' })
        }

        this.exportWAV = function (cb, type) {
            currCallback = cb || config.callback;
            type = type || config.type || 'audio/wav';
            if (!currCallback) throw new Error('Callback not set');
            worker.postMessage({
                command: 'exportWAV',
                type: type
            });
        }

        //Mp3 conversion
        worker.onmessage = function (e) {
            var loadingPanel = document.getElementById('itop_fog');
            var blob = e.data;
            var arrayBuffer;
            var fileReader = new FileReader();

            loadingPanel.style = '';
            fileReader.onload = function () {
                arrayBuffer = this.result;
                var buffer = new Uint8Array(arrayBuffer),
                data = parseWav(buffer);

                console.log(data);
                console.log("Converting to Mp3");

                encoderWorker.postMessage({
                    cmd: 'init', config: {
                        mode: 3,
                        channels: 1,
                        samplerate: data.sampleRate,
                        bitrate: data.bitsPerSample
                    }
                });

                encoderWorker.postMessage({ cmd: 'encode', buf: Uint8ArrayToFloat32Array(data.samples) });
                encoderWorker.postMessage({ cmd: 'finish' });
                encoderWorker.onmessage = function (e) {
                    if (e.data.cmd == 'data') {
                        console.log("Done converting to Mp3");

                        var mp3Blob = new Blob([new Uint8Array(e.data.buf)], { type: 'audio/mp3' });
                        uploadAudio(mp3Blob);

                        var url = 'data:audio/mp3;base64,' + encode64(e.data.buf);
                        var div = document.createElement('div');
                        var au = document.createElement('audio');
                        var hf = document.createElement('a');
                        var icon = document.createElement('span');

                        au.controls = true;
                        au.src = url;

                        hf.className = 'btn btn-default';
                        icon.className = 'glyphicon glyphicon-download-alt';

                        hf.href = url;
                        hf.download = 'audio_recording_' + new Date().getTime() + '.mp3';
                        div.appendChild(au);
                        hf.appendChild(icon);
                        div.appendChild(hf);
                        itop_audiorecords.innerHTML = '';
                        itop_audiorecords.appendChild(div);

                        loadingPanel.style = 'display:none';
                    }
                };
            };
            fileReader.readAsArrayBuffer(blob);
            currCallback(blob);
        }

        function encode64(buffer) {
            var binary = '',
                bytes = new Uint8Array(buffer),
                len = bytes.byteLength;
            for (var i = 0; i < len; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return window.btoa(binary);
        }

        function parseWav(wav) {
            function readInt(i, bytes) {
                var ret = 0,
                    shft = 0;
                while (bytes) {
                    ret += wav[i] << shft;
                    shft += 8;
                    i++;
                    bytes--;
                }
                return ret;
            }
            if (readInt(20, 2) != 1) throw 'Invalid compression code, not PCM';
            if (readInt(22, 2) != 1) throw 'Invalid number of channels, not 1';
            return {
                sampleRate: readInt(24, 4),
                bitsPerSample: readInt(34, 2),
                samples: wav.subarray(44)
            };
        }

        function Uint8ArrayToFloat32Array(u8a) {
            var f32Buffer = new Float32Array(u8a.length);
            for (var i = 0; i < u8a.length; i++) {
                var value = u8a[i << 1] + (u8a[(i << 1) + 1] << 8);
                if (value >= 0x8000) value |= ~0x7FFF;
                f32Buffer[i] = value / 0x8000;
            }
            return f32Buffer;
        }

        function uploadAudio(mp3Data) {
            var reader = new FileReader();
            reader.onload = function (event) {
                var fd = new FormData();

                // poodll expected parameters
                fd.append('datatype', 'uploadfile');
                fd.append('p1', 'html5AudioMP3Recorder');
                fd.append('fileext', 'mp3');
                fd.append('paramthree', 'audio');
                fd.append('requestid', document.getElementById('recorderid').value);

                fd.append('contextid', getParameterByName('p2'));
                fd.append('p2', getParameterByName('p2'));

                fd.append('component', getParameterByName('p3'));
                fd.append('p3', getParameterByName('p3'));

                fd.append('filearea', getParameterByName('p4'));
                fd.append('p4', getParameterByName('p4'));

                fd.append('itemid', getParameterByName('p5'));
                fd.append('p5', getParameterByName('p5'));

                fd.append('filedata', event.target.result);

                $.ajax({
                    type: 'POST',
                    url: window.location.origin + '/filter/poodll/poodllfilelib.php',
                    data: fd,
                    processData: false,
                    contentType: false
                }).done(function (data) {
                    console.log(data);

                    var resp = data.documentElement.outerHTML;
                    var start = resp.indexOf("success<error>");
                    if (start < 1) {
                        var errormatch = resp.match(/<error>([^<]*)<\/error>/);
                        var errormessage = errormatch[1];
                        console.log("A problem occurred:" + errormessage);
                        return;
                    }
                    var end = resp.indexOf("</error>");
                    var filename = resp.substring(start + 14, end);

                    var upc = document.getElementById(getParameterByName('updatecontrol'));
                    if (!upc) {
                        upc = parent.document.getElementById(getParameterByName('updatecontrol'));
                    }
                    if (upc) {
                        console.log("set inputs to replace old answer:" + upc.value + " by:" + filename);
                        upc.value = filename;

                        var callbackjs = getParameterByName('callbackjs');
                        console.log("callbackjs:" + callbackjs);
                        if (callbackjs && callbackjs != '') {
                            var ret = new Array();
                            ret[0] = document.getElementById('recorderid').value;
                            ret[1] = 'filesubmitted';
                            ret[2] = filename;
                            ret[3] = getParameterByName('updatecontrol');
                            var namespaces = callbackjs.split(".");
                            if (namespaces.length > 1) {
                                // call function namespaces[1] from object namespaces[0] from editor into parent iframe
                                parent.window[namespaces[0]][namespaces[1]](ret);
                            }
                        }
                    }
                    else {
                        console.log("impossible to find updatecontrol input, only draft has been saved");
                    }
                });
            };
            reader.readAsDataURL(mp3Data);
        }
        source.connect(this.node);
        this.node.connect(this.context.destination);
    };
    window.Recorder = Recorder;
})(window);
