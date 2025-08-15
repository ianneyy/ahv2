<?php
require_once '../includes/session.php';
$croptype = $_GET['croptype'] ?? '';
$quantity = $_GET['quantity'] ?? '';
$unit = $_GET['unit'] ?? '';
$submissionid = $_GET['submissionid'] ?? '';
$imagepath = $_GET['imagepath'] ?? '';
// Block non-farmers
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'farmer') {
  header("Location: ../auth/login.php");
  exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Submit Crop</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5" rel="stylesheet" type="text/css" />
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link href="https://cdn.jsdelivr.net/npm/daisyui@5/themes.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="../assets/style.css">

</head>

<body class="p-10">
  <div class="mx-auto max-w-2xl  ">

    <div class="flex gap-4 items-center mb-5">
      <a href="dashboard.php"
        class="inline-flex items-center gap-2 text-gray-600 hover:text-emerald-900 py-1 justify-center rounded-lg">
        <i data-lucide="chevron-left" class="w-6 h-6"></i>

        <span class="text-md">Home</span>
      </a>


    </div>
    <div class="  mt-5 mb-10 flex justify-between items-center ">

      <div>
        <h2 class="text-4xl text-emerald-900 font-semibold">Submit Your Crop</h2>
        <span class="text-lg text-gray-600">Provide your crop details and await verification</span>
      </div>

    </div>

  </div>


  <div class="mx-auto max-w-2xl py-8 px-4 border border-slate-200 shadow-md bg-white rounded-2xl mt-10">
    <div class="flex flex-col gap-5 px-5">
      <form action="submit_crop_action.php" method="POST" enctype="multipart/form-data" class="space-y-6">
        <!-- Crop Type Field -->
        <div class="flex flex-col space-y-2">
          <fieldset class="fieldset space-y-2">
            <legend class="fieldset-legend">Crop Type</legend>
            <!-- <input type="text" class="input" placeholder="Type here" /> -->
            <!-- <p class="label">Optional</p>
            <label for="croptype" class="text-sm font-medium text-gray-700">Crop Type</label> -->
            <select name="croptype" id="croptype" required onchange="setUnit(this.value)"
              class="w-full border border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg py-3 px-3 appearance-none bg-white bg-no-repeat bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20viewBox%3D%220%200%2024%2024%22%20stroke-width%3D%221.5%22%20stroke%3D%22currentColor%22%20class%3D%22w-6%20h-6%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20d%3D%22M19.5%208.25l-7.5%207.5-7.5-7.5%22%20%2F%3E%3C%2Fsvg%3E')] bg-[length:1em_1em] bg-[right_0.5em_center]">
              <option value="" class="text-slate-500">Select a crop</option>
              <option value="buko" <?php if ($croptype === 'buko')
                echo 'selected'; ?>>Buko</option>
              <option value="saba" <?php if ($croptype === 'saba')
                echo 'selected'; ?>>Saba</option>
              <option value="lanzones" <?php if ($croptype === 'lanzones')
                echo 'selected'; ?>>Lanzones</option>
              <option value="rambutan" <?php if ($croptype === 'rambutan')
                echo 'selected'; ?>>Rambutan</option>
            </select>
          </fieldset>

        </div>

        <div class="flex gap-2 justify-between">

          <!-- Quantity Field -->
          <div class="flex flex-col w-full">

            <fieldset class="fieldset space-y-2">
              <legend class="fieldset-legend">Quantity</legend>
              <input type="number" step="0.01" name="quantity" id="quantity" placeholder="Enter quantity"
                value="<?php echo htmlspecialchars($quantity); ?>" required
                class="w-full border border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg py-2 px-3">
            </fieldset>

          </div>

          <!-- Unit Field -->
          <div class="flex flex-col space-y-2  w-full">
            <fieldset class="fieldset space-y-2">
              <legend class="fieldset-legend">Unit</legend>
              <!-- <label for="unit" class="text-sm font-medium text-gray-700">Unit</label> -->
              <input type="text" name="unit" id="unit" value="<?php echo htmlspecialchars($unit); ?>" readonly required
                placeholder="Unit will be set automatically"
                class="w-full border border-slate-300 bg-gray-50 rounded-lg py-2 px-3">
            </fieldset>

          </div>
        </div>

        <!-- Image Upload Field -->
        <div class="flex flex-col space-y-2">
          <fieldset class="fieldset space-y-2">
            <legend class="fieldset-legend">Upload Image</legend>
            <!-- <label for="image" class="text-sm font-medium text-gray-700">Upload Image</label> -->
          </fieldset>

          <?php if (!empty($imagepath)): ?>
            <p class="text-xs text-gray-500">Current image:</p>
            <img src="../assets/uploads/<?php echo htmlspecialchars($imagepath); ?>" alt="Current"
              class="w-32 h-32 object-cover rounded border border-gray-300">

            <!-- Store old image path so PHP can keep it if no new file is uploaded -->
            <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($imagepath); ?>">
          <?php endif; ?>

          <input type="file" name="image" id="image" accept="image/*"
            class="w-full border border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg py-2 px-3
                   file:bg-green-50 file:text-green-700 file:border-green-500 file:rounded-lg file:py-1 file:px-3 cursor-pointer">
          </fieldset>

        </div>

        <!-- Submit Button -->
        <div class="pt-4">
          <?php if ($submissionid): ?>
            <button type="submit"
              class="w-full bg-emerald-600 text-white py-2 px-4 rounded-full hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
              Resubmit Crop
            </button>
            <input type="hidden" name="submissionid" value="<?php echo htmlspecialchars($submissionid); ?>">


          <?php else: ?>
            <button type="submit"
              class="w-full bg-emerald-600 text-white py-2 px-4 rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
              Submit Crop
            </button>
          <?php endif; ?>

        </div>
      </form>
    </div>
  </div>




  <script src="https://unpkg.com/lucide@latest"></script>
  <script>
    lucide.createIcons();
  </script>
</body>

</html>

<script>
  function setUnit(crop) {
    const unitField = document.getElementById('unit');
    if (crop === 'buko' || crop === 'saba') {
      unitField.value = 'pcs';
    } else if (crop === 'lanzones' || crop === 'rambutan') {
      unitField.value = 'kg';
    } else {
      unitField.value = '';
    }
  }
</script>