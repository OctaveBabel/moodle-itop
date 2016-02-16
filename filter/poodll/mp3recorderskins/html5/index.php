<html>
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" media="all">
    <link rel="stylesheet" href="html5skin.css">
    <link rel="stylesheet" type="text/css" href="../lib/styles.css">
</head>
<body>
    <div class="poodll_audiosdk_recording_cont">
            <div id="itop_fog" style="display:none">
                <img alt="loading" src="loading.gif" />
            </div>
        <div class="poodll_audiosdk_controlpanel btn-group btn-group-lg" role="group">
            <button onclick="startRecording(this);" name="record" id="poodll_audiosdk_record_button" class ="poodll_audiosdk_record_button btn btn-danger">
                <span class="poodll_audiosdk_record_button_text">REC</span>
            </button>
            <button onclick="stopRecording(this);" disabled name="stop" id="poodll_audiosdk_stop_button" class ="poodll_audiosdk_stop_button btn btn-default" > 
                <span class="glyphicon glyphicon-unchecked"></span>
            </button>
        </div>
        <div class="poodll_audiosdk_recorder_status_panel"></div>
        <div id="itop_audiorecords"></div>
    </div>
    <input type="hidden" id="recorderid" name="recorderid" value="<?php echo 'audiorecorder_' . time() . rand(10000, 999999); ?>" />
    <script src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
    <script src="recordmp3.js"></script>
</body>
</html>