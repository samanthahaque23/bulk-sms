<?php
require 'db.php';


if (isset($_POST["delete_folder"])) {
  $folder_id = $_POST["folder_id"];
  
  $sql_check_contacts = "SELECT COUNT(*) as contact_count FROM folder_contacts WHERE folder_id = ?";
  $stmt_check = $conn->prepare($sql_check_contacts);
  $stmt_check->bind_param("i", $folder_id);
  $stmt_check->execute();
  $result_check = $stmt_check->get_result();
  $row_check = $result_check->fetch_assoc();
  
  if ($row_check['contact_count'] > 0) {
      echo "<script type=\"text/javascript\">
              alert(\"Folder is not empty.\");
              window.location.href = 'index.php';
            </script>";
  } else {
      $sql_delete = "DELETE FROM folders WHERE id = ?";
      $stmt = $conn->prepare($sql_delete);
      $stmt->bind_param("i", $folder_id);

      if ($stmt->execute()) {
          echo "<script type=\"text/javascript\">
                  alert(\"Folder deleted successfully.\");
                  window.location.href = 'index.php';
                </script>";
      } else {
          echo "<script type=\"text/javascript\">
                  alert(\"Failed to delete folder.\");
                  window.location.href = 'index.php';
                </script>";
      }
  }
}


$sql_folders = "SELECT * FROM folders";
$folders_result = $conn->query($sql_folders);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Folders</title>

</head>
<body>
<div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h1 class="modal-title fs-5" id="exampleModalLabel">Modal title</h1>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
     
    <h2> Folders</h2>
    <ul>
        <?php
        if ($folders_result && $folders_result->num_rows > 0) {
            while ($folder = $folders_result->fetch_assoc()) {
                echo "<li>" . $folder['folder_name'] . " 
                        <form action=\"index.php\" method=\"post\" style=\"display: inline;\">
                            <input type=\"hidden\" name=\"folder_id\" value=\"" . $folder['id'] . "\">
                            <button type=\"submit\" name=\"delete_folder\">Delete</button>
                        </form>
                      </li>";
            }
        } else {
            echo "<li>No folders found</li>";
        }
        ?>
    </ul>
      </div>
     
    </div>
  </div>
</div>


</body>
</html>
