<?php
require_once '../includes/session.php';
require_once '../includes/db.php';
require_once '../includes/notify.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../vendor/autoload.php';


$toast_message = '';
$toast_type = ''; // 'success' or 'error'
// Handle delete if POSTed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $id = (int) $_POST['delete_id']; // cast to int for safety
    $deleteQuery = "DELETE FROM users WHERE id = $id";
    if ($conn->query($deleteQuery)) {
        $toast_message = "User deleted successfully.";
        $toast_type = 'success';
    } else {
        $toast_message = "Failed to delete user.";
        $toast_type = 'error';
    }
    
}
if (isset($_POST['send_invite'])) {
    $email = $conn->real_escape_string($_POST['invite_email']);
    $role = $conn->real_escape_string($_POST['invite_role']);

    function prettyRole($role)
    {
        $map = [
            'farmer' => 'Farmer',
            'businessPartner' => 'Business Partner',
            'businessOwner' => 'Business Owner',
            'transactionVerifier' => 'Transaction Verifier'
        ];
        return $map[$role] ?? $role;
    }
    $check = $conn->query("SELECT id FROM invitations WHERE email = '$email' LIMIT 1");

    if ($check && $check->num_rows > 0) {
        $toast_message = "User with email $email has already been invited.";
        $toast_type = 'error';
       
    } else {

        // Generate a unique token
        $token = bin2hex(random_bytes(16));

        // Store invitation in DB
        $insert = $conn->query("INSERT INTO invitations (email, role, token) VALUES ('$email', '$role', '$token')");

        if ($insert) {
            // Send invitation email
            $invite_link = "http://localhost/AHV2/auth/register.php?token=$token";
            $mail = new PHPMailer(true);

            $normalizeRole = prettyRole($role);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = '0322-2070@lspu.edu.ph';   // your Gmail
                $mail->Password = 'cfvp pdhf chui dvcs';     // Gmail App Password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port = 587;

                //Recipients
                $mail->setFrom('0322-2070@lspu.edu.ph', 'AHV2');
                $mail->addAddress($email);

                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Invitation to join AHV2';
                $mail->Body = "
                <p>Hello!</p>
                <p>You have been invited as a <strong>$normalizeRole</strong> on AHV2.</p>
                <p>Click the link below to register:</p>
                <p><a href='$invite_link'>$invite_link</a></p>
                <p>If you did not expect this email, please ignore it.</p>
            ";
                $mail->send();
                $toast_message = "Invitation sent to $email successfully.";
                $toast_type = 'success';
            } catch (Exception $e) {
                $toast_message = "Failed to send invitation email. Mailer Error: {$mail->ErrorInfo}";
                $toast_type = 'error';
            }

        } else {
            $toast_message = "Failed to store invitation.";
            $toast_type = 'error';
        }

    }
 

}


$query = "SELECT id, email, name, user_type FROM users";
$result = $conn->query($query);

$users = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}
?>


<?php
require_once '../includes/header.php';
?>

<div class="flex min-h-screen">

    <?php include 'includes/sidebar.php'; ?>

    <main class="flex-1 bg-[#FCFBFC] p-6 rounded-bl-4xl rounded-tl-4xl">

        <div class="lg:max-w-7xl" style=" margin: auto; padding: 20px;">
            <div class="flex items-center justify-center">
                <div id="bar" class="flex w-full justify-between items-center  mb-10  rounded-full">

                    <div class="flex flex-col">

                        <h2 class="text-2xl lg:text-4xl font-semibold text-emerald-800">User Management
                        </h2>
                        <span class="text-lg text-gray-600 ">View and manage user</span>
                    </div>



                    <div class="flex items-center gap-5">



                        <div class="relative">
                            <div
                                class="rounded-full p-2 flex items-center justify-center hover:bg-emerald-900 hover:text-white transition duration-300 ease-in-out">

                                <?php include '../includes/notification_ui.php'; ?>
                            </div>

                        </div>
                        <?php include 'includes/sm-sidebar.php'; ?>
                    </div>


                </div>
            </div>
            <div class="flex justify-end">
                <button onclick="addUserModal.showModal()"
                    class="flex items-center gap-2 bg-emerald-600 rounded-lg text-white px-4 py-2 text-sm">
                    <i data-lucide="user-plus" class="w-4 h-4"></i>
                    Add User</button>
            </div>
            <!-- Open the modal using ID.showModal() method -->
            <dialog id="addUserModal" class="modal modal-bottom sm:modal-middle">
                <div class="modal-box w-11/12 max-w-2xl">
                    <h3 class="text-lg font-bold">Add User</h3>
                    <p class="py-4">Send an invitation to join the platform with a specific role.</p>
                    <form method="POST" class=" mb-5">
                        <div class="flex flex-col">
                            <div class="flex items-center gap-3">

                                <label
                                    class="input w-full focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 focus:z-10 bg-transparent border-2  rounded-lg px-3 py-2  flex items-center gap-2 w-2/3">
                                    <i data-lucide="mail" class="h-[1em]"></i>

                                    <input type="text" name="invite_email" required class="grow placeholder-gray-600"
                                        placeholder="Email" />
                                </label>
                                <!-- <input type="email" name="invite_email" placeholder="Enter user email" required
                            class="border px-3 py-2 rounded w-1/3"> -->

                                <select name="invite_role" required class="select border-2 px-2 rounded-lg text-sm">
                                    <option value="" disabled selected>Select Role</option>
                                    <option value="farmer">Farmer</option>
                                    <option value="businessOwner">Business Partner</option>
                                    <option value="businessOwner">Business Owner</option>
                                </select>
                            </div>

                            <div class="flex justify-end mt-3">

                                <button type="submit" name="send_invite"
                                    class="bg-emerald-500 text-sm text-white px-4 py-2 rounded hover:bg-emerald-600">
                                    Send Invitation
                                </button>
                            </div>
                        </div>


                    </form>
                </div>
            </dialog>
            <div id="users-table"></div>
        </div>
    </main>
</div>
<script src="https://unpkg.com/gridjs/dist/gridjs.umd.js"></script>
<link href="https://unpkg.com/gridjs/dist/theme/mermaid.min.css" rel="stylesheet" />
<script src="https://unpkg.com/lucide@latest"></script>
<?php if ($toast_message): ?>
    <div class="toast toast-end">
        <div
            class="alert <?php echo $toast_type === 'success' ? 'alert-success text-emerald-900' : 'alert-error text-red-900'; ?> ">
            <?php echo htmlspecialchars($toast_message); ?>
        </div>
    </div>

    <script>
        setTimeout(() => {
            const toast = document.querySelector('.toast');
            if (toast) {
                toast.classList.add('hide');
                setTimeout(() => toast.remove(), 500);
            }
        }, 3000);
    </script>

    <style>
        .toast {
            transition: opacity 0.5s ease;
        }

        .toast.hide {
            opacity: 0;
        }
    </style>
<?php endif; ?>
<script>
    const usersData = <?php echo json_encode($users); ?>;

    new gridjs.Grid({
        columns: ['ID', 'Name', 'Email', 'User Type',

            {
                name: 'Action',
                formatter: (cell, row) => {
                    return gridjs.html(
                        `<div ">

                           
                            <button type="button" onclick="document.getElementById('logoutModal-${row.cells[0].data}').showModal()"
                                    class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600">
                                Delete
                            </button>
                            <dialog id="logoutModal-${row.cells[0].data}" class="modal">
                                <div class="modal-box">
                                <h3 class="text-lg font-bold">Remove User</h3>
                                <p class="py-4 text-md">Do you really want to remove ${row.cells[1].data}?</p>
                                 <form method="POST" style="display:inline;">
                                <div class="mt-6 flex justify-end gap-3">

                                    <input type="hidden" name="delete_id" value="${row.cells[0].data}" />

                                    <button onclick="document.getElementById('logoutModal-${row.cells[0].data}').close()" type="button"
                                    class="px-5 py-2.5 text-gray-600 hover:text-gray-800 border border-gray-300 hover:border-gray-400 rounded-full transition-colors">
                                    Cancel
                                    </button>
                                    <button type="submit"
                                    class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white font-medium rounded-full shadow-sm transition-colors">
                                    Yes, Delete
                                    </button>
                                </div>
                                </form>
                                </div>
                            </dialog>
                        
                        </div>`
                    );
                }
            }
        ],
        data: usersData.map(user => [user.id, user.name, user.email, user.user_type]),
        search: true,
        pagination: {
            enabled: true,
            limit: 10
        },
        sort: true,
        style: {
            table: {
                'width': '100%',
            }
        },
        className: {

            row: 'bg-gray-100 hover:bg-gray-200',
        },
        style: {
            table: {
                'border': 'none',
                'border-radius': '0.5rem',
                'font-size': '14px',
            },
            th: {
                'background-color': '#ECF5E9',
                'color': '#065f46',
                'font-weight': '600',
                'font-size': '12px',
            },
            td: {
                'font-size': '12px',
            }
        },
    }).render(document.getElementById("users-table"));
</script>
<script>
    lucide.createIcons();
    // anime({
    //   targets: '#bar',
    //   width: ['60%', '100%'],
    //   duration: 1500,
    //   easing: 'easeInExpo'
    // });
</script>