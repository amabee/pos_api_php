<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
require "conn.php";


class store_operations
{
    private $conn;

    public function __construct()
    {
        $this->conn = DatabaseConnection::getInstance()->getConnection();
    }


    public function getItem($json)
    {
        $json = json_decode($json, true);
        try {
            $sql = "SELECT * FROM `products` WHERE barcode = :barcode";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":barcode", $json["barcode"]);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {
                echo json_encode(array("success" => $result));
            } else {
                echo json_encode(array("error" => "No such item"));
            }
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }

    public function holdItems($json)
    {
        $json = json_decode($json, true);

        try {
            $sql = "INSERT INTO `heldorders`(`customer_id`, `cashier_id`, `created_at`, `updated_at`, `status`) VALUES (:customer_id, :cashier_id, :created_at, :updated_at, :status)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':customer_id' => $json['customer_id'],
                ':cashier_id' => $json['cashier_id'],
                ':created_at' => date('Y-m-d H:i:s'),
                ':updated_at' => date('Y-m-d H:i:s'),
                ':status' => 'active'
            ]);


            $held_order_id = $this->conn->lastInsertId();


            $itemSql = "INSERT INTO `heldorderitems`(`held_order_id`, `product_id`, `quantity`, `price_at_time`) VALUES (:held_order_id, :product_id, :quantity, :price_at_time)";
            $itemStmt = $this->conn->prepare($itemSql);

            foreach ($json['items'] as $item) {
                $itemStmt->execute([
                    ':held_order_id' => $held_order_id,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':price_at_time' => $item['price_at_time']
                ]);
            }

            echo json_encode(array("success" => "Order held successfully"));

        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }
    public function getHeldItems($json)
    {
        $json = json_decode($json, true);
        try {

            $sql = "
                SELECT heldorders.held_order_id, heldorders.customer_id, heldorders.created_at, heldorders.updated_at, heldorders.status,
                       heldorderitems.held_order_item_id, heldorderitems.product_id, heldorderitems.price_at_time, heldorderitems.quantity,
                       products.name, products.barcode, products.price
                FROM heldorders
                INNER JOIN heldorderitems ON heldorders.held_order_id = heldorderitems.held_order_id
                LEFT JOIN products ON heldorderitems.product_id = products.id  -- join with products table if needed
                WHERE heldorders.status = 'active' AND heldorders.cashier_id = :cashier_id
                ORDER BY heldorders.created_at DESC
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":cashier_id", $json["cashier_id"]);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(array("success" => $results));

        } catch (PDOException $e) {
            return "Error: " . $e->getMessage();
        }
    }

    public function getAllCustomerID()
    {
        try {
            $sql = "SELECT `customer_id` FROM `heldorders` WHERE `status` = 'active'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array("success" => $results));
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }

    public function getHeldItemsByCustomer($json)
    {

        $json = json_decode($json, true);

        $customer_id = isset($json["customer_id"]) ? $json["customer_id"] : null;
        $cashier_id = isset($json["cashier_id"]) ? $json["cashier_id"] : null;

        try {
            if (!$customer_id || !$cashier_id) {
                echo json_encode(array("error" => "Missing customer_id or cashier_id"));
                return;
            }

            $sql = "
            SELECT heldorders.held_order_id, heldorders.customer_id, heldorders.created_at, heldorders.updated_at, heldorders.status,
                   heldorderitems.held_order_item_id, heldorderitems.product_id, heldorderitems.price_at_time, heldorderitems.quantity,
                   products.name, products.barcode, products.price,
                   SUM(heldorderitems.price_at_time * heldorderitems.quantity) AS total_price
            FROM heldorders
            INNER JOIN heldorderitems ON heldorders.held_order_id = heldorderitems.held_order_id
            LEFT JOIN products ON heldorderitems.product_id = products.id
            WHERE heldorders.status = 'active' 
              AND heldorders.customer_id = :customer_id
              AND heldorders.cashier_id = :cashier_id
            GROUP BY heldorders.held_order_id, heldorders.customer_id, heldorders.created_at, heldorders.updated_at, heldorders.status,
                     heldorderitems.held_order_item_id, heldorderitems.product_id, heldorderitems.price_at_time, heldorderitems.quantity,
                     products.name, products.barcode, products.price
            ORDER BY heldorders.created_at DESC
        ";


            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":customer_id", $customer_id);
            $stmt->bindParam(":cashier_id", $cashier_id);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(array("success" => $results));

        } catch (PDOException $e) {
            echo json_encode(array("error" => "Error: " . $e->getMessage()));
        }
    }

    public function updateHeldItemsByCustomer($json)
    {
        $json = json_decode($json, true);

        $held_order_id = isset($json["held_order_id"]) ? $json["held_order_id"] : null;
        $status = isset($json["status"]) ? $json["status"] : null;

        try {
            if (!$held_order_id || !$status) {
                echo json_encode(array("error" => "Missing held_order_id or status"));
                return;
            }

            $sql = "
                UPDATE heldorders 
                SET status = :status
                WHERE held_order_id = :held_order_id
            ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":status", $status);
            $stmt->bindParam(":held_order_id", $held_order_id);
            $stmt->execute();

            echo json_encode(array("success" => "Status updated successfully"));

        } catch (PDOException $e) {
            echo json_encode(array("error" => "Error: " . $e->getMessage()));
        }
    }

    public function insertTransaction($json)
    {
        $json = json_decode($json, true);

        // Retrieve data from JSON
        $transaction_date = isset($json["transaction_date"]) ? $json["transaction_date"] : null;
        $cashier_id = isset($json["cashier_id"]) ? $json["cashier_id"] : null;
        $total_amount = isset($json["total_amount"]) ? $json["total_amount"] : null;
        $paid_amount = isset($json["paid_amount"]) ? $json["paid_amount"] : null;
        $change_amount = isset($json["change_amount"]) ? $json["change_amount"] : null;
        $status = isset($json["status"]) ? $json["status"] : null;
        $transaction_items = isset($json["transaction_items"]) ? $json["transaction_items"] : []; // Array of items with product_id, quantity, price, subtotal

        try {
            // Insert into transactions table
            $sql = "
        INSERT INTO transactions 
            (transaction_date, cashier_id, total_amount, paid_amount, change_amount, status, created_at) 
        VALUES 
            (:transaction_date, :cashier_id, :total_amount, :paid_amount, :change_amount, :status, NOW())
        ";

            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":transaction_date", $transaction_date);
            $stmt->bindParam(":cashier_id", $cashier_id);
            $stmt->bindParam(":total_amount", $total_amount);
            $stmt->bindParam(":paid_amount", $paid_amount);
            $stmt->bindParam(":change_amount", $change_amount);
            $stmt->bindParam(":status", $status);
            $stmt->execute();

            // Get the last inserted transaction ID
            $transaction_id = $this->conn->lastInsertId();

            // Insert into transaction_items table
            foreach ($transaction_items as $item) {
                $product_id = isset($item["product_id"]) ? $item["product_id"] : null;
                $quantity = isset($item["quantity"]) ? $item["quantity"] : null;
                $price = isset($item["price"]) ? $item["price"] : null;
                $subtotal = isset($item["subtotal"]) ? $item["subtotal"] : null;

                if ($product_id && $quantity) {
                    $sql_item = "
                INSERT INTO transaction_items 
                    (transaction_id, product_id, quantity, price, subtotal) 
                VALUES 
                    (:transaction_id, :product_id, :quantity, :price, :subtotal)
            ";

                    $stmt_item = $this->conn->prepare($sql_item);
                    $stmt_item->bindParam(":transaction_id", $transaction_id);
                    $stmt_item->bindParam(":product_id", $product_id);
                    $stmt_item->bindParam(":quantity", $quantity);
                    $stmt_item->bindParam(":price", $price);
                    $stmt_item->bindParam(":subtotal", $subtotal);
                    $stmt_item->execute();
                } else {
                    echo json_encode(array("error" => "Missing product_id or quantity"));
                    return;
                }
            }

            echo json_encode(array("success" => "Transaction and items inserted successfully"));

        } catch (PDOException $e) {
            echo json_encode(array("error" => "Error: " . $e->getMessage()));
        }
    }

    public function voidItem($json)
    {
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($data['cashier_id'], $data['product_id'], $data['void_reason'])) {
            return json_encode(array("error" => "Invalid input data or missing fields"));
        }

        try {
            $sql = "INSERT INTO void_items (cashier_id, product_id, amount ,void_reason, void_date, void_status)
                    VALUES (:cashier_id, :product_id, :amount ,:void_reason, NOW(), 'pending')";
            $stmt = $this->conn->prepare($sql);

            $stmt->bindParam(':cashier_id', $data['cashier_id'], PDO::PARAM_INT);
            $stmt->bindParam(':product_id', $data['product_id'], PDO::PARAM_INT);
            $stmt->bindParam(':amount', $data['amount'], PDO::PARAM_INT);
            $stmt->bindParam(':void_reason', $data['void_reason'], PDO::PARAM_STR);


            $result = $stmt->execute();

            if ($result) {
                $void_item_id = $this->conn->lastInsertId();
                return json_encode(array(
                    "success" => "Waiting for void approval",
                    "void_item_id" => $void_item_id
                ));
            } else {
                return json_encode(array("error" => "Something went wrong while trying to send void approval"));
            }

        } catch (PDOException $e) {
            return json_encode(array("error" => $e->getMessage()));
        }
    }
    public function checkForVoidItemStatus($json)
    {
        $json = json_decode($json, true);

        try {
            $sql = "SELECT void_status FROM void_items WHERE product_id = :product_id AND pid = :pid";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(":product_id", $json["product_id"], PDO::PARAM_INT);
            $stmt->bindParam(":pid", $json["pid"], PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result) {

                if ($result['void_status'] === 'voided') {
                    echo json_encode(array("status" => "voided"));
                } else {
                    echo json_encode(array("status" => "pending"));
                }
            } else {
                echo json_encode(array("error" => "Product ID not found"));
            }

        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }

    public function getMyTotalSales($json)
    {
        $json = json_decode($json, true); // Decode JSON input
        $cashierId = $json['cashier_id'] ?? null; // Get cashier_id from JSON

        if (!$cashierId) {
            return json_encode(array("error" => "Cashier ID is required."));
        }

        try {

            $salesSql = "SELECT SUM(total_amount) AS total_sales FROM transactions WHERE cashier_id = :cashier_id";
            $salesStmt = $this->conn->prepare($salesSql);
            $salesStmt->bindParam(":cashier_id", $cashierId, PDO::PARAM_INT);
            $salesStmt->execute();
            $salesResult = $salesStmt->fetch(PDO::FETCH_ASSOC);

            $voidsSql = "SELECT SUM(amount) AS total_voids FROM void_items WHERE void_status = 'voided' AND cashier_id = :cashier_id";
            $voidsStmt = $this->conn->prepare($voidsSql);
            $voidsStmt->bindParam(":cashier_id", $cashierId, PDO::PARAM_INT);
            $voidsStmt->execute();
            $voidsResult = $voidsStmt->fetch(PDO::FETCH_ASSOC);

            $totalSales = $salesResult['total_sales'] ?? 0;
            $totalVoids = $voidsResult['total_voids'] ?? 0;
            $netSales = $totalSales - $totalVoids;

            echo json_encode(array(
                "total_sales" => $totalSales,
                "total_voids" => $totalVoids,
                "net_sales" => $netSales
            ));
        } catch (PDOException $e) {
            echo json_encode(array("error" => $e->getMessage()));
        }
    }



}



$store_operations = new store_operations();


if ($_SERVER["REQUEST_METHOD"] == "GET" || $_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_REQUEST['operation']) && isset($_REQUEST['json'])) {
        $operation = $_REQUEST['operation'];
        $json = $_REQUEST['json'];

        switch ($operation) {
            case 'getItem':
                echo $store_operations->getItem($json);
                break;

            case 'holdItems':
                echo $store_operations->holdItems($json);
                break;

            case 'getHoldItems':
                echo $store_operations->getHeldItems($json);
                break;

            case 'getAllCustomerID':
                echo $store_operations->getAllCustomerID();
                break;

            case 'getHeldItemsFromCustomer':
                echo $store_operations->getHeldItemsByCustomer($json);
                break;

            case 'updateHeldItemsStatus':
                echo $store_operations->updateHeldItemsByCustomer($json);
                break;

            case 'insertTransactions':
                echo $store_operations->insertTransaction($json);
                break;

            case "voidItem":
                echo $store_operations->voidItem($json);
                break;

            case "checkForItemVoidStatus":
                echo $store_operations->checkForVoidItemStatus($json);
                break;

            case "getMyTotalSales":
                echo $store_operations->getMyTotalSales($json);
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