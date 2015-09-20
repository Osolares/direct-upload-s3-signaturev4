<?php


// TODO Enter your AWS credentials
define('AWS_ACCESS_KEY', '');
define('AWS_SECRET', '');


/**
 * Get all the necessary details to directly upload a private file to S3
 * asynchronously with JavaScript.
 *
 * @param string $s3Bucket your bucket's name on s3.
 * @param string $region   the bucket's location, see here for details: http://amzn.to/1FtPG6r
 * @param string $acl      the visibility/permissions of your file, see details: http://amzn.to/18s9Gv7
 *
 * @return array ['url', 'inputs'] the forms url to s3 and any inputs the form will need.
 */
function getS3Details($s3Bucket, $region, $acl = 'private') {

    // Options and Settings
    $algorithm = "AWS4-HMAC-SHA256";
    $service = "s3";
    $date = gmdate('Ymd\THis\Z');
    $shortDate = gmdate('Ymd');
    $requestType = "aws4_request";
    $expires = '86400'; // 24 Hours
    $successStatus = '201';
    $url = '//' . $s3Bucket . '.' . $service . '-' . $region . '.amazonaws.com';

    // Step 1: Generate the Scope
    $scope = [
        AWS_ACCESS_KEY,
        $shortDate,
        $region,
        $service,
        $requestType
    ];
    $credentials = implode('/', $scope);

    // Step 2: Making a Base64 Policy
    $policy = [
        'expiration' => gmdate('Y-m-d\TG:i:s\Z', strtotime('+6 hours')),
        'conditions' => [
            ['bucket' => $s3Bucket],
            ['acl' => $acl],
            ['starts-with', '$key', ''],
            ['starts-with', '$Content-Type', ''],
            ['success_action_status' => $successStatus],
            ['x-amz-credential' => $credentials],
            ['x-amz-algorithm' => $algorithm],
            ['x-amz-date' => $date],
            ['x-amz-expires' => $expires],
        ]
    ];
    $base64Policy = base64_encode(json_encode($policy));

    // Step 3: Signing your Request (Making a Signature)
    $dateKey = hash_hmac('sha256', $shortDate, 'AWS4' . AWS_SECRET, true);
    $dateRegionKey = hash_hmac('sha256', $region, $dateKey, true);
    $dateRegionServiceKey = hash_hmac('sha256', $service, $dateRegionKey, true);
    $signingKey = hash_hmac('sha256', $requestType, $dateRegionServiceKey, true);

    $signature = hash_hmac('sha256', $base64Policy, $signingKey);

    // Step 4: Build form inputs
    // This is the data that will get sent with the form to S3
    $inputs = [
        'Content-Type' => '',
        'acl' => $acl,
        'success_action_status' => $successStatus,
        'policy' => $base64Policy,
        'X-amz-credential' => $credentials,
        'X-amz-algorithm' => $algorithm,
        'X-amz-date' => $date,
        'X-amz-expires' => $expires,
        'X-amz-signature' => $signature
    ];

    return compact('url', 'inputs');
}

// TODO Enter your bucket and region details
$s3FormDetails = getS3Details('', '');

?>

<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Direct Upload Example</title>
        <style>
            body { font-family: sans-serif; }
            .container { width: 600px; margin: 50px auto; }
            form { margin-bottom: 50px; }
            input[type="text"] { width: 100%; }
            label { display: block; margin-top: 10px; }
            .progress {
                display: none;
                position: relative;
                width: 100%; height: 15px;
                background: #C7DA9F;
                border-radius: 10px;
                overflow: hidden;
                margin-top: 30px;
            }
            .bar {
                position: absolute;
                top: 0; left: 0;
                width: 0; height: 15px;
                background: #85C220;
            }
            .bar.red { background: tomato; }
        </style>
    </head>
    <body>

        <div class="container">

            <h1>Direct Upload</h1>

            <!-- Direct Upload to S3 Form -->
            <form action="<?php echo $s3FormDetails['url']; ?>"
                  method="POST"
                  enctype="multipart/form-data"
                  class="direct-upload">

                <?php foreach ($s3FormDetails['inputs'] as $name => $value) { ?>
                    <input type="hidden" name="<?php echo $name; ?>" value="<?php echo $value; ?>">
                <?php } ?>

                <!-- Key is the file's name on S3 and can be filled in with JS -->
                <input type="hidden" name="key" value="${filename}">
                <input type="file" name="file">

                <!-- Progress Bar to show upload completion percentage -->
                <div class="progress"><div class="bar"></div></div>

            </form>

            <!-- This area will be filled with our results -->
            <div>
                <label for="upload_original_name">Original Filename:</label>
                <input type="text" name="upload_original_name" id="upload_original_name">

                <label for="upload_custom_name">Filename on S3:</label>
                <input type="text" name="upload_custom_name" id="upload_custom_name">

                <label for="size">File Size:</label>
                <input type="text" name="size" id="size">

                <label for="url">URL:</label>
                <input type="text" name="url" id="url">

                <label for="etag">ETag:</label>
                <input type="text" name="etag" id="etag">
            </div>

        </div>

        <!-- Start of the JavaScript -->
        <!-- Load jQuery & jQuery UI (Needed for the FileUpload Plugin) -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/jquery-ui.min.js"></script>

        <!-- Load the FileUpload Plugin (more info @ https://github.com/blueimp/jQuery-File-Upload) -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-file-upload/9.5.7/jquery.fileupload.js"></script>

        <script>
            $(document).ready(function () {

                // Assigned to variable for later use.
                var form = $('.direct-upload');

                form.fileupload({
                    url: form.attr('action'),
                    type: form.attr('method'),
                    datatype: 'xml',
                    add: function (event, data) {

                        // Give the file being uploaded it's current content-type
                        // It doesn't retain it otherwise.
                        form.find('input[name="Content-Type"]').val(data.originalFiles[0].type);

                        // Message on unLoad.
                        // Shows 'Are you sure you want to leave message', just to confirm.
                        window.onbeforeunload = function () {
                            return 'You have unsaved changes.';
                        };

                        // Actually submit to form, sending the data.
                        data.submit();
                        $('.progress').slideDown('fast');
                    },
                    progress: function (e, data) {
                        // This is what makes everything really cool, thanks to that callback
                        // you can now update the progress bar based on the upload progress.
                        var percent = Math.round((data.loaded / data.total) * 100);
                        $('.bar').css('width', percent + '%');
                    },
                    fail: function (e, data) {
                        // Remove the 'unsaved changes' message.
                        window.onbeforeunload = null;
                        $('.bar').css('width', '100%').addClass('red');
                    },
                    done: function (event, data) {
                        window.onbeforeunload = null;

                        // Fill the name field with the file's name.
                        var original = data.originalFiles[0];
                        $('#upload_original_name').val(original.name);
                        $('#upload_custom_name').val(original.name);
                        $('#size').val(original.size);

                        var s3Result = data.result.documentElement.children;
                        $('#url').val(s3Result[0].innerHTML);
                        $('#etag').val(s3Result[3].innerHTML);
                    }
                });
            });
        </script>
    </body>
</html>