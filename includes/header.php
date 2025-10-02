<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="../assets/style.css">
    <link href="https://cdn.jsdelivr.net/npm/gridjs/dist/theme/mermaid.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>


    <!-- Grid.js JS -->
</head>

<body class="bg-[#ECF5E9]">
     <dialog id="logoutModal" class="modal">
            <div class="modal-box">
              <h3 class="text-lg font-bold">Log Out</h3>
              <p class="py-4">Do you really want to log out now?</p>
              <div class="mt-6 flex justify-end gap-3">
                <button onclick="logoutModal.close()" type="button"
                  class="px-5 py-2.5 text-gray-600 hover:text-gray-800 border border-gray-300 hover:border-gray-400 rounded-full transition-colors">
                  Cancel
                </button>
                <a href="../auth/logout.php"
                  class="px-5 py-2.5 bg-red-500 hover:bg-red-600 text-white font-medium rounded-full shadow-sm transition-colors">
                  Yes, Log Out
                </a>
              </div>
            </div>
          </dialog>