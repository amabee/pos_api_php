<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require "conn.php";

class Admin_API
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }

    public function getAllItems()
    {

        $sql = "SELECT * FROM products";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode($result);

    }

    public function insertProduct($json)
    {
        $json = json_decode($json, true);
        try {

            $sql = "INSERT INTO `products`(`barcode`, `name`, `price`, `stock_quantity`) VALUES (:barcode, :name, :price, :stock)";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":barcode", $json["barcode"], PDO::PARAM_STR);
            $stmt->bindParam(":name", $json["name"], PDO::PARAM_STR);
            $stmt->bindParam(":price", $json["price"]);
            $stmt->bindParam(":stock", $json["stock"], PDO::PARAM_INT);
            $result = $stmt->execute();


            if ($result) {
                echo json_encode(array("success" => "Successfully Added product"));
            } else {
                echo json_encode(array("error" => "Something went wrong adding the product"));
            }


        } catch (PDOException $e) {
            echo json_encode(array("error" => "Exception Error: {$e}"));
        }
    }

    public function removeProduct($json)
    {
        $json = json_decode($json, true);

        try {
            $sql = "DELETE FROM `products` WHERE barcode = :barcode";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":barcode", $json["barcode"], PDO::PARAM_STR);
            $result = $stmt->execute();

            if ($result) {
                echo json_encode(array("success" => "Successfully deleted selected item"));
            } else {
                echo json_encode(array("error" => "Something went wrong deleting the selected item"));
            }
        } catch (PDOException $e) {
            echo json_encode(array("error" => "Exception Error: {$e}"));
        }
    }

    public function updateProduct($json)
    {
        $json = json_decode($json, true);

        try {
            $sql = "UPDATE `products` SET `name`= :name,`price`= :price,`stock_quantity`= :stock,`updated_at`= NOW() WHERE `barcode` = :barcode";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":barcode", $json["barcode"], PDO::PARAM_STR);
            $stmt->bindParam(":name", $json["name"], PDO::PARAM_STR);
            $stmt->bindParam(":price", $json["price"]);
            $stmt->bindParam(":stock", $json["stock"], PDO::PARAM_INT);

            $res = $stmt->execute();
            $affectedRows = $stmt->rowCount();

            if ($res && $affectedRows > 0) {
                error_log("Rows affected: " . $affectedRows);
                echo json_encode(array("success" => "The product is updated successfully"));
            } else {
                error_log("No rows affected. SQL Error: " . json_encode($stmt->errorInfo()));
                echo json_encode(array("error" => "No rows were updated"));
            }
        } catch (PDOException $e) {
            error_log("PDO Exception: " . $e->getMessage());
            echo json_encode(array("error" => "Exception Error: {$e}"));
        }
    }

    public function getAlLCashier()
    {
        try {
            $sql = "SELECT * FROM `users` WHERE role = 'cashier'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($result) > 0) {
                echo json_encode(array("success" => $result));
            } else {
                echo json_encode(array("error" => "No cashier found"));
            }
        } catch (PDOException $e) {
            echo json_encode(array("error" => "Exception Error @: {$e}"));
        }
    }

    public function createCashier($json)
    {
        $json = json_decode($json, true);

        $targetDir = "images/";

        if (isset($_FILES['image'])) {
            $file = $_FILES['image'];

            $targetFile = $targetDir . basename($file["name"]);
            $fullPath = "/" . $targetFile;
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $validExtensions = array("jpg", "jpeg", "png", "gif");

            if (in_array($imageFileType, $validExtensions)) {
                if (move_uploaded_file($file["tmp_name"], $targetFile)) {
                    try {
                        $sql = "INSERT INTO `users`(`firstname`, `lastname`, `username`, `password`, `image`, `role`, `created_at`) 
                                VALUES (:firstname, :lastname, :username, :password , :image, 'cashier', NOW())";
                        $stmt = $this->conn->prepare($sql);
                        $stmt->bindParam(":firstname", $json["firstname"], PDO::PARAM_STR);
                        $stmt->bindParam(":lastname", $json["lastname"], PDO::PARAM_STR);
                        $stmt->bindParam(":username", $json["username"], PDO::PARAM_STR);
                        $stmt->bindParam(":password", $json["password"], PDO::PARAM_STR);
                        $stmt->bindParam(":image", $fullPath, PDO::PARAM_STR);

                        $result = $stmt->execute();

                        if ($result) {
                            echo json_encode(array("success" => "Added Cashier Successfully"));
                        } else {
                            echo json_encode(array("error" => "Something went wrong while adding the cashier"));
                        }
                    } catch (PDOException $e) {
                        echo json_encode(array("error" => "Exception Error: {$e}"));
                    }
                } else {
                    echo json_encode(array("error" => "Sorry, there was an error uploading your file."));
                }
            } else {
                echo json_encode(array("error" => "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed."));
            }
        } else {
            echo json_encode(array("error" => "No image file was uploaded."));
        }
    }



}

$admin_api = new Admin_API();



if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_REQUEST['operation']) && isset($_REQUEST['json'])) {
        $operation = $_REQUEST['operation'];
        $json = $_REQUEST['json'];

        switch ($operation) {
            case 'getAllItem':
                echo $admin_api->getAllItems();
                break;

            case 'insertProduct':
                echo $admin_api->insertProduct($json);
                break;
            case 'deleteProduct':
                echo $admin_api->removeProduct($json);
                break;

            case 'updateProduct':
                echo $admin_api->updateProduct($json);
                break;

            case 'getAllCashier':
                echo $admin_api->getAlLCashier();
                break;
            case 'addCashier':
                echo $admin_api->createCashier($json);
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