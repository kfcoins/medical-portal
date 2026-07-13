<?php
// index.php - Main entry point
ini_set('display_errors', 0);
error_reporting(E_ALL);

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$route = isset($_GET['route']) ? $_GET['route'] : '';
$routeParts = explode('/', $route);

$controller = isset($routeParts[0]) && $routeParts[0] !== '' ? $routeParts[0] : null;
$action = isset($routeParts[1]) ? $routeParts[1] : null;
$id = isset($routeParts[2]) ? $routeParts[2] : null;

// Read JSON input if available
$input = json_decode(file_get_contents("php://input"), true);
if (is_array($input)) {
    $_POST = array_merge($_POST, $input);
}

switch ($controller) {
    case 'auth':
        require_once 'controllers/AuthController.php';
        $authController = new AuthController();
        $authController->handleRequest($action);
        break;
    case 'admin':
        require_once 'controllers/AdminController.php';
        $adminController = new AdminController();
        $adminController->handleRequest($action, $id);
        break;
    case 'agents':
        require_once 'controllers/AgentController.php';
        $agentController = new AgentController();
        // action could be an ID (numeric) or a specific action like "profile"
        $agentController->handleRequest($action, $id);
        break;
    case 'medicines':
        require_once 'controllers/MedicineController.php';
        $medicineController = new MedicineController();
        $medicineController->handleRequest($action, $id);
        break;
    case 'orders':
        require_once 'controllers/OrderController.php';
        $orderController = new OrderController();
        $orderController->handleRequest($action, $id);
        break;
    case 'contact':
        require_once 'controllers/ContactController.php';
        $contactController = new ContactController();
        $contactController->handleRequest($action);
        break;
    case 'messages':
        require_once 'controllers/MessageController.php';
        $messageController = new MessageController();
        $messageController->handleRequest($action, $id);
        break;
    case 'patient':
        require_once 'controllers/PatientController.php';
        $patientController = new PatientController();
        $patientController->handleRequest($action, $id);
        break;
    default:
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "API endpoint not found. route=".$route]);
        break;
}
?>
