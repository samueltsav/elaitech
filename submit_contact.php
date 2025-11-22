<?php
header('Content-Type: application/json');

// Include configuration
require_once 'config.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => '',
    'errors' => []
];

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}

try {
    // Get and sanitize form data
    $firstName = isset($_POST['firstName']) ? sanitizeInput($_POST['firstName']) : '';
    $lastName = isset($_POST['lastName']) ? sanitizeInput($_POST['lastName']) : '';
    $email = isset($_POST['email']) ? sanitizeInput($_POST['email']) : '';
    $phone = isset($_POST['phone']) ? sanitizeInput($_POST['phone']) : '';
    $service = isset($_POST['service']) ? sanitizeInput($_POST['service']) : '';
    $message = isset($_POST['message']) ? sanitizeInput($_POST['message']) : '';
    $newsletter = isset($_POST['newsletter']) ? 1 : 0;

    // Validate required fields
    if (empty($firstName)) {
        $response['errors'][] = 'First name is required';
    }
    
    if (empty($lastName)) {
        $response['errors'][] = 'Last name is required';
    }
    
    if (empty($email)) {
        $response['errors'][] = 'Email is required';
    } elseif (!validateEmail($email)) {
        $response['errors'][] = 'Invalid email format';
    }
    
    if (empty($service)) {
        $response['errors'][] = 'Please select a service';
    }
    
    if (empty($message)) {
        $response['errors'][] = 'Message is required';
    } elseif (strlen($message) < 10) {
        $response['errors'][] = 'Message must be at least 10 characters';
    }

    // If there are validation errors, return them
    if (!empty($response['errors'])) {
        $response['message'] = 'Please fix the following errors:';
        echo json_encode($response);
        exit;
    }

    // Get database connection
    $pdo = getDatabaseConnection();
    
    if (!$pdo) {
        $response['message'] = 'Database connection failed. Please try again later.';
        echo json_encode($response);
        exit;
    }

    // Get client information
    $ipAddress = getClientIP();
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';

    // Prepare SQL statement
    $sql = "INSERT INTO contact_submissions 
            (first_name, last_name, email, phone, service, message, newsletter, ip_address, user_agent) 
            VALUES 
            (:first_name, :last_name, :email, :phone, :service, :message, :newsletter, :ip_address, :user_agent)";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $stmt->bindParam(':first_name', $firstName);
    $stmt->bindParam(':last_name', $lastName);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':phone', $phone);
    $stmt->bindParam(':service', $service);
    $stmt->bindParam(':message', $message);
    $stmt->bindParam(':newsletter', $newsletter);
    $stmt->bindParam(':ip_address', $ipAddress);
    $stmt->bindParam(':user_agent', $userAgent);

    // Execute the statement
    if ($stmt->execute()) {
        $response['success'] = true;
        $response['message'] = 'Thank you for contacting us! We will get back to you soon.';
        
        // Optional: Send email notification
        sendEmailNotification($firstName, $lastName, $email, $phone, $service, $message);
        
    } else {
        $response['message'] = 'Failed to submit form. Please try again.';
    }

} catch (PDOException $e) {
    error_log("Database Error: " . $e->getMessage());
    $response['message'] = 'An error occurred while processing your request. Please try again later.';
} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    $response['message'] = 'An unexpected error occurred. Please try again later.';
}

// Return JSON response
echo json_encode($response);

function sendEmailNotification($firstName, $lastName, $email, $phone, $service, $message) {
    // Multiple recipients (comma-separated)
    $to = "info@ellaitech.com, ellaitech09@gmail.com";

    $subject = "New Contact Form Submission";

    $emailBody = "
        <html>
        <body>
            <h2>New Contact Form Submission</h2>
            <p><strong>Name:</strong> {$firstName} {$lastName}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Phone:</strong> {$phone}</p>
            <p><strong>Service:</strong> {$service}</p>
            <p><strong>Message:</strong><br>{$message}</p>
        </body>
        </html>
    ";

    // Proper headers
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Ellai Tech & Construction <info@ellaitech.com>\r\n";
    $headers .= "Reply-To: {$email}\r\n";

    // Use the 5th parameter to set the envelope sender
    mail($to, $subject, $emailBody, $headers, "-f info@ellaitech.com");
}

?>