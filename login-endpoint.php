<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require "conn.php";

class auth_endpoint
{

    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    public function login($json)
    {
        try {
            $json = json_decode($json, true);
            $sql = "SELECT id, username, image,role FROM `users` WHERE username = :username AND password = :password";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":username", $json["username"]);
            $stmt->bindParam(":password", $json["password"]);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result > 0) {
                echo json_encode(array("success" => $result));
            } else {
                echo json_encode(array("error" => "No such user!"));
            }
        } catch (PDOException $e) {

            echo json_encode(array("error" => $e->getMessage()));
        }
    }

}

$auth_endpoint = new auth_endpoint();

if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_REQUEST['operation']) && isset($_REQUEST['json'])) {
        $operation = $_REQUEST['operation'];
        $json = $_REQUEST['json'];

        switch ($operation) {
            case 'login':
                echo $auth_endpoint->login($json);
                break;

            default:
                echo json_encode(["error" => "Invalid operation"]);
                break;
        }
    } else {
        echo json_encode(["error" => "Missing parameters"]);
    }
} else {
    echo json_encode(["error" => "Invalid request method"]);
}
?>