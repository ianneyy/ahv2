<?php
require_once '../includes/db.php';
require_once '../includes/session.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST["name"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $user_type = $_POST["user_type"];

    $sql = "INSERT INTO users (name, email, password, user_type) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssss", $name, $email, $password, $user_type);
    $stmt->execute();

    echo "User registered successfully!";
}
?>

<form method="POST">
  Name: <input type="text" name="name" required><br>
  Email: <input type="email" name="email" required><br>
  Password: <input type="password" name="password" required><br>
  User Type:
  <select name="user_type" required>
    <option value="businessOwner">Business Owner</option>
    <option value="farmer">Farmer</option>
    <option value="businessPartner">Business Partner</option>
    <option value="transactionVerifier">Transaction Verifier</option>
  </select><br>
  <button type="submit">Register</button>
</form>
