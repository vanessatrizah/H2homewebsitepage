<?php
date_default_timezone_set('Etc/UTC');

// Load SMTP config
$formConfigFile = file_get_contents("rd-mailform.config.json");
$formConfig = json_decode($formConfigFile, true);

try {
    require './phpmailer/PHPMailerAutoload.php';

    // Manually set recipient
    $recipientEmail = 'vanessatrizah@gmail.com';

    // Reject local/test IPs
    function getRemoteIPAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    if (preg_match('/^(127\.|192\.168\.)/', getRemoteIPAddress())) {
        die('MF002'); // Localhost block
    }

    // Load email template
    $template = file_get_contents('rd-mailform.tpl');

    // Subject by form type
    $subject = 'A message from your site visitor';
    if (isset($_POST['form-type'])) {
        switch ($_POST['form-type']) {
            case 'subscribe': $subject = 'Subscribe request'; break;
            case 'order':     $subject = 'Order request';     break;
            case 'contact':   default: break;
        }
    } else {
        die('MF004'); // Missing form-type
    }

    // Replace template tags
    if (isset($_POST['email'])) {
        $template = str_replace(
            array("<!-- #{FromState} -->", "<!-- #{FromEmail} -->"),
            array("Email:", $_POST['email']),
            $template
        );
    }

    if (isset($_POST['message'])) {
        $template = str_replace(
            array("<!-- #{MessageState} -->", "<!-- #{MessageDescription} -->"),
            array("Message:", $_POST['message']),
            $template
        );
    }

    // Insert additional fields into template
    preg_match("/(<!-- #\{BeginInfo\} -->)(.|\s)*?(<!-- #\{EndInfo\} -->)/", $template, $tmp, PREG_OFFSET_CAPTURE);
    foreach ($_POST as $key => $value) {
        if (!in_array($key, ['counter', 'email', 'message', 'form-type', 'g-recaptcha-response']) && !empty($value)) {
            $info = str_replace(
                ["<!-- #{BeginInfo} -->", "<!-- #{InfoState} -->", "<!-- #{InfoDescription} -->"],
                ["", ucfirst($key) . ':', htmlspecialchars($value)],
                $tmp[0][0]
            );
            $template = str_replace("<!-- #{EndInfo} -->", $info, $template);
        }
    }

    $template = str_replace(
        ["<!-- #{Subject} -->", "<!-- #{SiteName} -->"],
        [$subject, $_SERVER['SERVER_NAME']],
        $template
    );

    // Initialize PHPMailer
    $mail = new PHPMailer();

    if ($formConfig['useSmtp']) {
        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Debugoutput = 'html';
        $mail->Host = $formConfig['host'];
        $mail->Port = $formConfig['port'];
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = $formConfig['encryption']; // e.g. "ssl" or "tls"
        $mail->Username = $formConfig['username'];
        $mail->Password = $formConfig['password'];
    }

    // From address
    $mail->From = $formConfig['username'];
    $mail->FromName = isset($_POST['name']) ? $_POST['name'] : "Site Visitor";

    // Attach file if present
    if (isset($_FILES['file']) && $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $mail->AddAttachment($_FILES['file']['tmp_name'], $_FILES['file']['name']);
    }

    // Send to vanessatrizah
    $mail->addAddress($recipientEmail);

    $mail->CharSet = 'utf-8';
    $mail->Subject = $subject;
    $mail->MsgHTML($template);

    // Send it!
    if ($mail->send()) {
        die('MF000'); // Success
    } else {
        die('MF255'); // PHPMailer failure
    }

} catch (phpmailerException $e) {
    die('MF254'); // PHPMailer error
} catch (Exception $e) {
    die('MF255'); // General error
}
