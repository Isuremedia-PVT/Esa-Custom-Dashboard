<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';

class EmailController {
    private $smtpHost = 'smtp.titan.email';
    private $smtpUser = 'esa@cocoonbaby.com.au';
    private $smtpPass = 'Scottandharry123!!';
    private $smtpPort = 465;
    private $fromEmail = 'esa@cocoonbaby.com.au';
    private $fromName = 'Cocoonbaby Record System';

    public function sendNotification($toEmail, $subject, $body) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = $this->smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->smtpUser;
            $mail->Password   = $this->smtpPass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = $this->smtpPort;
            $mail->CharSet    = 'UTF-8';

            // Recipients
            $mail->setFrom($this->fromEmail, $this->fromName);
            $mail->addAddress($toEmail);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            error_log("Email Error to ($toEmail): {$mail->ErrorInfo}");
            return ['success' => false, 'message' => "Email could not be sent. Mailer Error: {$mail->ErrorInfo}"];
        }
    }

    public function sendWelcomeEmail($toEmail, $name, $username, $password) {
        $loginLink = "https://cocoonbaby.online/signin.php";
        $subject = "Welcome to Cocoonbaby Record System";
        
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 30px; text-align: center; color: white;'>
                    <h1 style='margin: 0; font-size: 24px;'>Welcome, $name!</h1>
                </div>
                <div style='padding: 30px; background-color: #ffffff;'>
                    <p style='color: #4b5563; font-size: 16px; margin-top: 0;'>Hi $name,</p>
                    <p style='color: #4b5563; font-size: 16px;'>Your account has been created successfully. You can now access the Cocoonbaby Management System with the following credentials:</p>
                    
                    <div style='background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;'>Username:</td>
                                <td style='padding: 8px 0; color: #111827; font-size: 14px; font-weight: bold;'>$username</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Email:</td>
                                <td style='padding: 8px 0; color: #111827; font-size: 14px; font-weight: bold;'>$toEmail</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Password:</td>
                                <td style='padding: 8px 0; color: #111827; font-size: 14px; font-weight: bold;'>$password</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='$loginLink' style='display: inline-block; padding: 14px 28px; background-color: #4f46e5; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;'>Login to Your Account</a>
                    </div>
                    
                    <p style='color: #9ca3af; font-size: 14px; margin-top: 30px; text-align: center;'>
                        For security reasons, we recommend changing your password after your first login.
                    </p>
                </div>
                <div style='padding: 20px; background-color: #f3f4f6; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; color: #9ca3af; font-size: 12px;'>&copy; ".date('Y')." CocoonBaby Record Management System. All rights reserved.</p>
                </div>
            </div>
        ";
        return $this->sendNotification($toEmail, $subject, $body);
    }

    public function sendPasswordResetEmail($toEmail, $resetLink) {
        $subject = "Reset Your Password - Cocoonbaby";
        
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); padding: 30px; text-align: center; color: white;'>
                    <h1 style='margin: 0; font-size: 24px;'>Password Reset Request</h1>
                </div>
                <div style='padding: 30px; background-color: #ffffff;'>
                    <p style='color: #4b5563; font-size: 16px; margin-top: 0;'>Hello,</p>
                    <p style='color: #4b5563; font-size: 16px;'>We received a request to reset your password. Click the button below to choose a new one. This link will expire in 1 hour.</p>
                    
                    <div style='text-align: center; margin: 35px 0;'>
                        <a href='$resetLink' style='display: inline-block; padding: 14px 28px; background-color: #ef4444; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;'>Reset My Password</a>
                    </div>
                    
                    <p style='color: #6b7280; font-size: 14px;'>If the button above doesn't work, copy and paste this link into your browser:</p>
                    <p style='color: #4f46e5; font-size: 13px; word-break: break-all;'>$resetLink</p>
                    
                    <p style='color: #9ca3af; font-size: 14px; margin-top: 30px;'>
                        If you didn't request a password reset, you can safely ignore this email.
                    </p>
                </div>
                <div style='padding: 20px; background-color: #f3f4f6; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; color: #9ca3af; font-size: 12px;'>&copy; ".date('Y')." CocoonBaby Record Management System. All rights reserved.</p>
                </div>
            </div>
        ";
        return $this->sendNotification($toEmail, $subject, $body);
    }

    public function notifyFinalSubmission($adminEmail, $patientName, $noteType) {
        $subject = "📝 New Final Submission: $patientName ($noteType)";
        $body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;'>
                <div style='background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 30px; text-align: center; color: white;'>
                    <h1 style='margin: 0; font-size: 24px;'>New Shift Note Received</h1>
                </div>
                <div style='padding: 30px; background-color: #ffffff;'>
                    <p style='color: #4b5563; font-size: 16px; margin-top: 0;'>Hello Admin,</p>
                    <p style='color: #4b5563; font-size: 16px;'>A new shift note has been finalized by a staff member or patient. Here are the details:</p>
                    
                    <div style='background-color: #f9fafb; border-radius: 8px; padding: 20px; margin: 25px 0;'>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-size: 14px; width: 40%;'>Patient Name:</td>
                                <td style='padding: 8px 0; color: #111827; font-size: 14px; font-weight: bold;'>$patientName</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Shift/Note Type:</td>
                                <td style='padding: 8px 0; color: #111827; font-size: 14px; font-weight: bold;'>$noteType</td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Status:</td>
                                <td style='padding: 8px 0;'><span style='background-color: #dcfce7; color: #166534; padding: 4px 12px; border-radius: 9999px; font-size: 12px; font-weight: bold;'>FINALIZED</span></td>
                            </tr>
                            <tr>
                                <td style='padding: 8px 0; color: #6b7280; font-size: 14px;'>Submitted At:</td>
                                <td style='padding: 8px 0; color: #111827; font-size: 14px;'>".date('Y-m-d H:i:s')."</td>
                            </tr>
                        </table>
                    </div>
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='https://cocoonbaby.online/patient_question_details.php' style='display: inline-block; padding: 14px 28px; background-color: #4f46e5; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; font-size: 16px;'>Review Submission</a>
                    </div>
                </div>
                <div style='padding: 20px; background-color: #f3f4f6; text-align: center; border-top: 1px solid #e5e7eb;'>
                    <p style='margin: 0; color: #9ca3af; font-size: 12px;'>&copy; ".date('Y')." CocoonBaby Record Management System. All rights reserved.</p>
                </div>
            </div>
        ";
        return $this->sendNotification($adminEmail, $subject, $body);
    }

}
?>
