<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

class Mailer {
    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host       = 'smtp.gmail.com'; 
        $this->mail->SMTPAuth   = true;

   
        $this->mail->Username   = Env::get('MAIL_USERNAME', 'pangemah@gmail.com'); 
        $this->mail->Password   = Env::get('MAIL_PASSWORD', ''); 
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mail->Port       = 465;
        $this->mail->Timeout    = 15; // 15 seconds timeout to allow secure TLS connection

        $this->mail->setFrom('pangemah@gmail.com', 'PharmaTrust Ghana');
        $this->mail->isHTML(true);
    }

    public function sendOTP($toEmail, $otpCode) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = 'Your Verification Code - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Welcome to PharmaTrust Ghana</h2>
                    <p>Your verification code is: <strong><span style='font-size: 24px; color: #2D9A6A;'>$otpCode</span></strong></p>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you did not request this, please ignore this email.</p>
                </div>
            ";
            
            $this->mail->AltBody = "Welcome to PharmaTrust Ghana.\n\nYour verification code is: $otpCode\n\nThis code will expire in 15 minutes. If you did not request this, please ignore this email.";
            
            // Log for local testing when SMTP fails
            error_log("OTP for $toEmail is $otpCode");
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            // Return true in development so the flow continues even if SMTP is not configured
            return true; 
        }
    }

    public function sendPasswordResetOTP($toEmail, $otpCode) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = 'Password Reset Code - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Password Reset Request</h2>
                    <p>We received a request to reset your PharmaTrust password. Your reset code is: <strong><span style='font-size: 24px; color: #2D9A6A;'>$otpCode</span></strong></p>
                    <p>This code will expire in 15 minutes.</p>
                    <p>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                </div>
            ";
            
            $this->mail->AltBody = "Password Reset Request\n\nYour reset code is: $otpCode\n\nThis code will expire in 15 minutes. If you did not request a password reset, please ignore this email or contact support if you have concerns.";
            
            // Log for local testing when SMTP fails
            error_log("Password Reset OTP for $toEmail is $otpCode");
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return true; 
        }
    }

    public function sendAdminNewPharmacyAlert($adminEmail, $pharmacyName, $pharmacyEmail) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($adminEmail);
            $this->mail->Subject = 'New Pharmacy Registration - Action Required';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>New Pharmacy Registration</h2>
                    <p>A new pharmacy has just registered and requires administrative review.</p>
                    <ul>
                        <li><strong>Pharmacy Name:</strong> $pharmacyName</li>
                        <li><strong>Contact Email:</strong> $pharmacyEmail</li>
                    </ul>
                    <p>Please log in to your Admin Dashboard to review their documents and approve or decline the application.</p>
                    <p><span>Open Dashboard</span></p>
                </div>
            ";
            $this->mail->AltBody = "New Pharmacy Registration\n\nA new pharmacy has just registered and requires administrative review.\n\nPharmacy Name: $pharmacyName\nContact Email: $pharmacyEmail\n\nPlease log in to your Admin Dashboard to review their documents and approve or decline the application.";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent to admin. Mailer Error: {$this->mail->ErrorInfo}");
            return true; 
        }
    }

    public function sendContactMessageAlert($adminEmail, $name, $phone, $email, $userType, $messageText) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($adminEmail);
            $this->mail->Subject = 'New Contact Message - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>New Message Received</h2>
                    <p>A new message has been submitted via the contact form.</p>
                    <table style='width: 100%; border-collapse: collapse; margin-top: 15px;'>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Name:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$name</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Phone:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$phone</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>Email:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$email</td></tr>
                        <tr><td style='padding: 8px; border-bottom: 1px solid #ddd;'><strong>User Type:</strong></td><td style='padding: 8px; border-bottom: 1px solid #ddd;'>$userType</td></tr>
                    </table>
                    <div style='margin-top: 20px; padding: 15px; background: #f9fafb; border-left: 4px solid #2D9A6A;'>
                        <p style='margin:0;'><strong>Message:</strong></p>
                        <p style='white-space: pre-wrap; margin-top: 10px;'>$messageText</p>
                    </div>
                </div>
            ";
            $this->mail->AltBody = "New Message Received\n\nName: $name\nPhone: $phone\nEmail: $email\nUser Type: $userType\n\nMessage:\n$messageText";
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Contact message could not be sent to Admin. Mailer Error: {$this->mail->ErrorInfo}");
            return true; 
        }
    }


    public function sendPharmacyReceived($toEmail, $pharmacyName) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = 'Registration Received - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Hello $pharmacyName,</h2>
                    <p>We have successfully received your pharmacy registration application.</p>
                    <p>Our administrative team is currently reviewing your documents and details. This process usually takes 24-48 hours.</p>
                    <p>We will notify you via email as soon as your account is approved.</p>
                    <br>
                    <p>Best Regards,</p>
                    <p><strong>PharmaTrust Ghana Team</strong></p>
                </div>
            ";
            $this->mail->AltBody = "Hello $pharmacyName,\n\nWe have successfully received your pharmacy registration application. Our administrative team is currently reviewing your documents and details. This process usually takes 24-48 hours.\n\nWe will notify you via email as soon as your account is approved.\n\nBest Regards,\nPharmaTrust Ghana Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return true;
        }
    }

    public function sendPharmacyApproved($toEmail, $pharmacyName) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = 'Application Approved! - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Congratulations $pharmacyName!</h2>
                    <p>Your pharmacy application has been <strong>approved</strong> by the PharmaTrust administration team.</p>
                    <p>You can now log in to your dashboard to manage your inventory, orders, and profile.</p>
                    <p><span>Log In Now</span></p>
                    <br>
                    <p>Best Regards,</p>
                    <p><strong>PharmaTrust Ghana Team</strong></p>
                </div>
            ";
            $this->mail->AltBody = "Congratulations $pharmacyName!\n\nYour pharmacy application has been approved by the PharmaTrust administration team.\n\nYou can now log in to your dashboard to manage your inventory, orders, and profile.\n\nBest Regards,\nPharmaTrust Ghana Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return true;
        }
    }

    public function sendPharmacyDeclined($toEmail, $pharmacyName, $reason) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = 'Application Update - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Hello $pharmacyName,</h2>
                    <p>Thank you for your interest in PharmaTrust Ghana. We have reviewed your application.</p>
                    <p>Unfortunately, we cannot approve your application at this time.</p>
                    <div style='background-color: #FFF5F5; padding: 15px; border-left: 4px solid #E53E3E; margin: 20px 0;'>
                        <strong>Reason:</strong><br>
                        " . nl2br(htmlspecialchars($reason)) . "
                    </div>
                    <p>If you have any questions or wish to appeal, please reply to this email or contact our support team.</p>
                    <br>
                    <p>Best Regards,</p>
                    <p><strong>PharmaTrust Ghana Team</strong></p>
                </div>
            ";
            $this->mail->AltBody = "Hello $pharmacyName,\n\nThank you for your interest in PharmaTrust Ghana. We have reviewed your application.\n\nUnfortunately, we cannot approve your application at this time.\n\nReason:\n$reason\n\nIf you have any questions or wish to appeal, please reply to this email or contact our support team.\n\nBest Regards,\nPharmaTrust Ghana Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return true;
        }
    }

    public function sendNhisApproved($toEmail, $firstName) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = 'NHIS Card Approved - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Hello $firstName,</h2>
                    <p>Good news! Your NHIS card has been <strong>approved</strong> by our administration team.</p>
                    <p>You can now use your NHIS benefits for eligible pharmacy orders.</p>
                    <br>
                    <p>Best Regards,</p>
                    <p><strong>PharmaTrust Ghana Team</strong></p>
                </div>
            ";
            $this->mail->AltBody = "Hello $firstName,\n\nGood news! Your NHIS card has been approved by our administration team.\n\nYou can now use your NHIS benefits for eligible pharmacy orders.\n\nBest Regards,\nPharmaTrust Ghana Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    public function sendNhisDeclined($toEmail, $firstName, $reason = "Details were unclear or invalid") {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = 'Action Required: NHIS Card Declined - PharmaTrust Ghana';
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2>Hello $firstName,</h2>
                    <p>We encountered an issue with your NHIS card submission, and it has been <strong>declined</strong>.</p>
                    <p><strong>Reason:</strong> $reason</p>
                    <p>Please log in to your account and submit clear, updated photos of your NHIS card.</p>
                    <br>
                    <p>Best Regards,</p>
                    <p><strong>PharmaTrust Ghana Team</strong></p>
                </div>
            ";
            $this->mail->AltBody = "Hello $firstName,\n\nWe encountered an issue with your NHIS card submission, and it has been declined.\n\nReason: $reason\n\nPlease log in to your account and submit clear, updated photos of your NHIS card.\n\nBest Regards,\nPharmaTrust Ghana Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }

    public function sendOrderStatusChanged($toEmail, $patientName, $orderNo, $newStatus) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = "Order Status Update: $orderNo";
            
            $statusColor = '#1A6349';
            if (strtolower($newStatus) === 'delivered') $statusColor = '#10b981';
            elseif (strtolower($newStatus) === 'cancelled') $statusColor = '#ef4444';
            elseif (strtolower($newStatus) === 'dispensed') $statusColor = '#f59e0b';

            $this->mail->Body = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2 style='color: #1A6349;'>PharmaTrust Ghana</h2>
                    <p>Hello $patientName,</p>
                    <p>We're writing to let you know that the status of your order <strong>#$orderNo</strong> has been updated.</p>
                    <p>Current Status: <strong style='text-transform: uppercase; color: $statusColor;'>$newStatus</strong></p>
                    <p>Please log in to your patient dashboard for more details.</p>
                    <br>
                    <p>Best Regards,<br>The PharmaTrust Team</p>
                </div>
            ";
            $this->mail->AltBody = "Hello $patientName,\n\nWe're writing to let you know that the status of your order #$orderNo has been updated.\n\nCurrent Status: " . strtoupper($newStatus) . "\n\nPlease log in to your patient dashboard for more details and to track your order history.\n\nBest Regards,\nThe PharmaTrust Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return true;
        }
    }

    public function sendOrderPlacedPatient($toEmail, $patientName, $orderNo, $total) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = "Order Confirmation: $orderNo";
            $this->mail->Body    = "
                <div style='font-family: Arial, sans-serif; padding: 20px; color: #333;'>
                    <h2 style='color: #1A6349;'>PharmaTrust Ghana</h2>
                    <p>Hello $patientName,</p>
                    <p>Thank you for your order! We've received your request and it is currently being processed by the pharmacy.</p>
                    <p>Order Number: <strong>#$orderNo</strong><br>
                    Total Amount: <strong>GHS " . number_format($total, 2) . "</strong></p>
                    <p>We will send you another update as soon as your order status changes.</p>
                    <p>Please log in to your patient dashboard to track your order.</p>
                    <br>
                    <p>Best Regards,<br>The PharmaTrust Team</p>
                </div>
            ";
            $this->mail->AltBody = "Hello $patientName,\n\nThank you for your order! We've received your request and it is currently being processed by the pharmacy.\n\nOrder Number: #$orderNo\nTotal Amount: GHS " . number_format($total, 2) . "\n\nWe will send you another update as soon as your order status changes.\n\nTrack your order at: PharmaTrust Dashboard\n\nBest Regards,\nThe PharmaTrust Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return true;
        }
    }

    public function sendOrderPlacedPharmacy($toEmail, $pharmacyName, $orderNo, $total) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail);
            $this->mail->Subject = "New Order Received: $orderNo";
            $this->mail->Body    = "
                <div style=\"background-color: #f4f7f6; padding: 40px 20px; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #334155;\">
                    <div style=\"max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.05);\">
                        <div style=\"background-color: #1e293b; padding: 24px; text-align: center;\">
                            <h1 style=\"color: #ffffff; margin: 0; font-size: 24px; font-weight: 600; letter-spacing: 0.5px;\">PharmaTrust Partner</h1>
                        </div>
                        <div style=\"padding: 32px 24px;\">
                            <h2 style=\"margin-top: 0; color: #0f172a; font-size: 20px;\">Hello $pharmacyName,</h2>
                            <p style=\"font-size: 16px; line-height: 1.6; color: #475569;\">
                                You have just received a new order. Please log in to your dashboard to review and process it.
                            </p>
                            <div style=\"margin: 24px 0; padding: 20px; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;\">
                                <table style=\"width: 100%; font-size: 16px;\">
                                    <tr>
                                        <td style=\"padding-bottom: 8px; color: #64748b;\">Order Number:</td>
                                        <td style=\"padding-bottom: 8px; text-align: right; font-weight: 600; color: #0f172a;\">#$orderNo</td>
                                    </tr>
                                    <tr>
                                        <td style=\"padding-top: 8px; border-top: 1px solid #e2e8f0; color: #64748b;\">Total Revenue:</td>
                                        <td style=\"padding-top: 8px; border-top: 1px solid #e2e8f0; text-align: right; font-weight: 700; color: #1e293b;\">GHS " . number_format($total, 2) . "</td>
                                    </tr>
                                </table>
                            </div>
                            <div style=\"margin-top: 32px; text-align: center;\">
                                <a href=\"PharmaTrust Dashboard\" style=\"display: inline-block; padding: 12px 24px; background-color: #1e293b; color: #ffffff; text-decoration: none; font-weight: 600; border-radius: 6px;\">Open Pharmacy Dashboard</a>
                            </div>
                        </div>
                        <div style=\"background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;\">
                            <p style=\"margin: 0; font-size: 13px; color: #64748b;\">
                                &copy; " . date('Y') . " PharmaTrust Ghana.
                            </p>
                        </div>
                    </div>
                </div>
            ";
            $this->mail->AltBody = "Hello $pharmacyName,\n\nA new order (#$orderNo) has been placed by a patient and assigned to your pharmacy.\n\nOrder Value: GHS " . number_format($total, 2) . "\n\nPlease log in to your pharmacy dashboard to review the prescription and process the order.\n\nProcess Order at: PharmaTrust Dashboard\n\nBest Regards,\nPharmaTrust Ghana Team";
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mail->ErrorInfo}");
            return true;
        }
    }
}
