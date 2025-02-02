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

    public function toggleCashierStatus($json)
    {
        $json = json_decode($json, true);

        try {

            $fetchSql = "SELECT `status` FROM `users` WHERE id = :id";
            $fetchStmt = $this->conn->prepare($fetchSql);
            $fetchStmt->bindParam(":id", $json["id"], PDO::PARAM_INT);
            $fetchStmt->execute();
            $currentStatus = $fetchStmt->fetchColumn();

            $newStatus = ($currentStatus === 'active') ? 'inactive' : 'active';

            $updateSql = "UPDATE `users` SET `status` = :newStatus WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->bindParam(":newStatus", $newStatus, PDO::PARAM_STR);
            $updateStmt->bindParam(":id", $json["id"], PDO::PARAM_INT);
            $result = $updateStmt->execute();

            $affectedRows = $updateStmt->rowCount();

            if ($result && $affectedRows > 0) {
                echo json_encode(array("success" => "The cashier status is updated successfully"));
            } else {
                echo json_encode(array("error" => "No rows were updated or cashier not found"));
            }
        } catch (PDOException $e) {
            echo json_encode(array("error" => "Exception Error: {$e->getMessage()}"));
        }
    }

    public function updateCashierInfo($json)
    {
        $json = json_decode($json, true);
        $id = $json['id'] ?? null;

        if (!$id) {
            echo json_encode(array("error" => "ID is required"));
            return;
        }

        $imagePath = null;

        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $targetDir = "images/";
            $targetFile = $targetDir . basename($file["name"]);
            $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
            $validExtensions = array("jpg", "jpeg", "png", "gif");

            if (in_array($imageFileType, $validExtensions)) {
                if (move_uploaded_file($file["tmp_name"], $targetFile)) {
                    $imagePath = "/" . $targetFile;
                } else {
                    echo json_encode(array("error" => "Sorry, there was an error uploading your file."));
                    return;
                }
            } else {
                echo json_encode(array("error" => "Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed."));
                return;
            }
        }

        try {
            $sql = "UPDATE `users` SET `firstname` = :firstname, `lastname` = :lastname, `username` = :username, 
                `password` = :password";

            if ($imagePath) {
                $sql .= ", `image` = :image";
            }

            $sql .= " WHERE id = :id";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(":firstname", $json["firstname"], PDO::PARAM_STR);
            $stmt->bindParam(":lastname", $json["lastname"], PDO::PARAM_STR);
            $stmt->bindParam(":username", $json["username"], PDO::PARAM_STR);
            $stmt->bindParam(":password", $json["password"], PDO::PARAM_STR);

            if ($imagePath) {
                $stmt->bindParam(":image", $imagePath, PDO::PARAM_STR);
            }

            $stmt->bindParam(":id", $id, PDO::PARAM_INT);

            $result = $stmt->execute();
            $affectedRows = $stmt->rowCount();

            if ($result && $affectedRows > 0) {
                echo json_encode(array("success" => "Cashier information updated successfully"));
            } else {
                echo json_encode(array("error" => "No rows were updated"));
            }
        } catch (PDOException $e) {
            echo json_encode(array("error" => "Exception Error: {$e->getMessage()}"));
        }
    }

    public function getTotalSales()
    {
        try {
            $sql = "SELECT SUM(total_amount) AS total_amount_sum
                    FROM transactions
                    WHERE YEAR(transaction_date) = YEAR(CURDATE())";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            // Cast the result to float. If no result is found, use 0.0.
            $totalAmountSum = isset($result['total_amount_sum']) ? (float) $result['total_amount_sum'] : 0.0;

            // Encode as JSON
            echo json_encode(array("success" => $totalAmountSum));

        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }

    public function getTotalDaySale()
    {
        try {

            $sql = "SELECT SUM(total_amount) AS total_amount_sum
                    FROM transactions
                    WHERE DATE(transaction_date) = CURDATE()";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $totalAmountSum = isset($result['total_amount_sum']) ? (float) $result['total_amount_sum'] : 0.0;

            echo json_encode(array("success" => $totalAmountSum));

        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }

    public function getTransactionByCashiers()
    {
        try {

            $sql = "SELECT u.id AS cashier_id, u.firstname, u.lastname, u.image, SUM(t.total_amount) AS total_amount_sum
                    FROM transactions t
                    INNER JOIN users u ON t.cashier_id = u.id
                    WHERE u.role = 'cashier'
                    AND DATE(t.transaction_date) = CURDATE()
                    GROUP BY u.id, u.firstname, u.lastname";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(array("success" => $transactions));

        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }


    public function getMostPurchasedItems()
    {
        try {
            $sql = "SELECT ti.product_id, p.name, SUM(ti.quantity) AS total_quantity
                    FROM transaction_items ti
                    INNER JOIN transactions t ON ti.transaction_id = t.id
                    INNER JOIN products p ON ti.product_id = p.id
                    WHERE DATE(t.transaction_date) = CURDATE()
                    GROUP BY ti.product_id, p.name
                    ORDER BY total_quantity DESC
                    LIMIT 5";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $topItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(array("success" => $topItems));
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }
    public function getOrderCount()
    {
        try {
            $sql = "SELECT COUNT(*) AS total_orders FROM transactions WHERE DATE(transaction_date) = CURDATE()";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalOrderCount = isset($result["total_orders"]) ? (int) $result['total_orders'] : 0;

            echo json_encode(array("success" => $totalOrderCount));
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));

        }
    }

  
    public function getVoidItems()
    {
        try {

            $sql = "SELECT 
                    void_items.pid AS void_id, 
                    void_items.product_id, 
                    void_items.cashier_id, 
                    void_items.void_reason, 
                    void_items.void_date, 
                    void_items.void_status,
                    products.name AS product_name, 
                    users.firstname AS cashier_firstname, 
                    users.lastname AS cashier_lastname
                FROM void_items
                INNER JOIN products ON void_items.product_id = products.id 
                INNER JOIN users ON void_items.cashier_id = users.id
                WHERE void_items.void_status = 'pending';
                    ";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute();

            $voidedItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return json_encode(array("success" => $voidedItems));

        } catch (PDOException $e) {
            return json_encode(array("error" => $e->getMessage()));
        }
    }

    public function updateVoidItem($json)
    {
        $json = json_decode($json, true);

        try {

            $sql = "UPDATE void_items 
                    SET void_status = 'voided' 
                    WHERE pid = :id AND void_status = 'pending'";

            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(":id", $json["id"], PDO::PARAM_INT);

            $result = $stmt->execute();

            if ($result && $stmt->rowCount() > 0) {
                echo json_encode(array("success" => "Void status updated successfully"));
            } else {
                echo json_encode(array("error" => "No matching records found or status already updated"));
            }
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
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

            case 'deactivateCashier':
                echo $admin_api->toggleCashierStatus($json);
                break;

            case 'updateCashier':
                echo $admin_api->updateCashierInfo($json);
                break;

            case 'getAllTotalSales':
                echo $admin_api->getTotalSales();
                break;

            case 'getTotalDaySales':
                echo $admin_api->getTotalDaySale();
                break;

            case "getSalesByCashier":
                echo $admin_api->getTransactionByCashiers();
                break;

            case "getOrderCount":
                echo $admin_api->getOrderCount();
                break;

            case "getMostPurchasedItem":
                echo $admin_api->getMostPurchasedItems();
                break;


            case "getVoidItems":
                echo $admin_api->getVoidItems();
                break;

            case "updateVoidItems":
                echo $admin_api->updateVoidItem($json);
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