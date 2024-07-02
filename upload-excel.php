<?php
require 'vendor/autoload.php';  // Ensure this path is correct

use PhpOffice\PhpSpreadsheet\IOFactory;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sms";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

function startsWithUKCode($phoneNumber) {
    return preg_match('/^44/', $phoneNumber);
}

if (isset($_POST["import"])) {
    $filename = $_FILES["file"]["tmp_name"];

    if ($_FILES["file"]["size"] > 0) {
        try {
            $spreadsheet = IOFactory::load($filename);
            $worksheet = $spreadsheet->getActiveSheet();

            foreach ($worksheet->getRowIterator() as $row) {
                $cellIterator = $row->getCellIterator();
                $cellIterator->setIterateOnlyExistingCells(FALSE);

                $data = [];
                foreach ($cellIterator as $cell) {
                    $data[] = $cell->getValue();
                }

                $phoneNumber = $data[2];
                if (!startsWithUKCode($phoneNumber)) {
                    echo "<script type=\"text/javascript\">
                            alert(\"Phone number '$phoneNumber' does not start with '+44'. Skipping.\");
                          </script>";
                    continue; 
                }

                $sql = "INSERT INTO users (first_name, last_name, phone_number) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $data[0], $data[1], $data[2]);

                if ($stmt->execute()) {
                    echo "<script type=\"text/javascript\">
                            alert(\"Excel File has been successfully Imported.\");
                          </script>";
                } else {
                    echo "<script type=\"text/javascript\">
                            alert(\"Error: " . $stmt->error . "\");
                          </script>";
                }
            }

        } catch (Exception $e) {
            echo "<script type=\"text/javascript\">
                    alert(\"Error loading file: " . $e->getMessage() . "\");
                  </script>";
        }
    }
}

$sql_fetch = "SELECT first_name, last_name, phone_number FROM users";
$result = $conn->query($sql_fetch);

?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Excel File into MySQL and Send Bulk SMS using PHP</title>
</head>
<body>
    <form class="form-horizontal" action="" method="post" name="upload_excel" enctype="multipart/form-data">
        <div>
            <label>Choose Excel File:</label>
            <input type="file" name="file" accept=".xls,.xlsx">
            <button type="submit" name="import">Import</button>
        </div>
    </form>

    <hr>

    <h2>Imported Data</h2>
    <form action="" method="post">
        <table border="1">
            <thead>
                <tr>
                    <th>Select</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Phone Number</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>";
                        echo "<td><input type='checkbox' name='phone_numbers[]' value='" . $row['phone_number'] . "'></td>";
                        echo "<td>" . $row['first_name'] . "</td>";
                        echo "<td>" . $row['last_name'] . "</td>";
                        echo "<td>" . $row['phone_number'] . "</td>";
                        echo "</tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>No data found</td></tr>";
                }
                ?>
            </tbody>
        </table>

        <hr>

        <div>
            <label>Message:</label><br>
            <textarea name="message" rows="4" cols="50"></textarea><br>
            <button type="submit" name="send_sms_selected">Send SMS to Selected Numbers</button>
        </div>
    </form>

    <?php
    if (isset($_POST["send_sms_selected"])) {
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        $apiKey = 'NTM0MzM1NDc3NzRjNDE3NDMwNmU2MjcwNzk0MzMzNDI=';  
        $sender = 'samantha';  
        $message = $_POST["message"];

        if (isset($_POST['phone_numbers']) && !empty($_POST['phone_numbers'])) {
            $selected_numbers = $_POST['phone_numbers'];

            $numbers = implode(',', $selected_numbers);
            $data = array(
                'apikey' => $apiKey,
                'numbers' => $numbers,
                'sender' => $sender,
                'message' => $message
            );

            $ch = curl_init('https://api.textlocal.in/send/');
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $insert_sql = "INSERT INTO sms_info (phone_number, message) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_sql);
            $stmt->bind_param("ss", $number, $message);

            foreach ($selected_numbers as $number) {
                $stmt->execute();
            }

            echo "<script type=\"text/javascript\">
                    alert(\"Bulk SMS Sent Successfully.\");
                  </script>";
        } else {
            echo "<script type=\"text/javascript\">
                    alert(\"Please select at least one phone number.\");
                  </script>";
        }

        $conn->close();
    }
    ?>
</body>
</html>
