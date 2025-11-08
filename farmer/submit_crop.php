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

<?php
require_once '../includes/header.php';
?>
<div class="flex min-h-screen">
  <?php include 'includes/sidebar.php'; ?>
  <main class="flex-1 bg-[#FCFBFC] lg:p-6 rounded-bl-4xl rounded-tl-4xl">
    <div class="lg:max-w-7xl" style=" margin: auto; font-family: Arial; padding: 20px;">
      <!-- <div class="mt-5 mb-10 flex justify-between items-center ">

        <div>
          <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold">Submit Your Crop</h2>
          <span class="text-md lg:text-lg text-gray-600">Provide your crop details and await verification</span>
        </div>

      </div> -->
      <div class="flex flex-col lg:flex-row lg:justify-between  lg:ml-4 mt-5">
        <div class="flex justify-between items-center">

          <div>
            <h2 class="text-2xl lg:text-4xl text-emerald-900 font-semibold ">Submit Your Crop</h2>
            <span class="text-md lg:text-lg text-gray-600 ">Provide your crop details and await verification</span>
          </div>

          <?php include 'includes/sm-sidebar.php'; ?>
        </div>
      </div>

      <div class=" mt-10">
        <div class="flex flex-col gap-5">
          <form action="submit_crop_action.php" method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Crop Type Field -->
            <div class="flex flex-col space-y-2">
              <fieldset class="fieldset space-y-2">
                <legend class="fieldset-legend text-emerald-900">Crop Type</legend>
                <!-- <input type="text" class="input" placeholder="Type here" /> -->
                <!-- <p class="label">Optional</p>
            <label for="croptype" class="text-sm font-medium text-gray-700">Crop Type</label> -->
                <select name="croptype" id="croptype" required onchange="setUnit(this.value)"
                  class="w-full border border-slate-300 focus:border-green-500 focus:ring-green-500 rounded-lg py-3 px-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 appearance-none bg-white bg-no-repeat bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20fill%3D%22none%22%20viewBox%3D%220%200%2024%2024%22%20stroke-width%3D%221.5%22%20stroke%3D%22currentColor%22%20class%3D%22w-6%20h-6%22%3E%3Cpath%20stroke-linecap%3D%22round%22%20stroke-linejoin%3D%22round%22%20d%3D%22M19.5%208.25l-7.5%207.5-7.5-7.5%22%20%2F%3E%3C%2Fsvg%3E')] bg-[length:1em_1em] bg-[right_0.5em_center]">
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
                  <legend class="fieldset-legend text-emerald-900">Quantity</legend>
                  <input type="number" step="0.01" name="quantity" id="quantity" placeholder="Enter quantity"
                    value="<?php echo htmlspecialchars($quantity); ?>" required
                    class="w-full border border-slate-300 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500  rounded-lg py-2 px-3">
                </fieldset>

              </div>

              <!-- Unit Field -->
              <div class="flex flex-col space-y-2  w-full">
                <fieldset class="fieldset space-y-2">
                  <legend class="fieldset-legend text-emerald-900">Unit</legend>
                  <!-- <label for="unit" class="text-sm font-medium text-gray-700">Unit</label> -->
                  <input type="text" name="unit" id="unit" value="<?php echo htmlspecialchars($unit); ?>" readonly
                    required placeholder="Unit will be set automatically"
                    class="w-full border border-slate-300 bg-gray-50 rounded-lg py-2 px-3">
                </fieldset>

              </div>
            </div>

            <!-- Image Upload Field -->
            <div class="flex flex-col space-y-3">
              <fieldset class="fieldset space-y-2">
                <legend class="fieldset-legend text-emerald-900">Upload Image</legend>
                <span class="text-xs text-gray-500">Drag or drop your image here or click to upload</span>
                <!-- <label for="image" class="text-sm font-medium text-gray-700">Upload Image</label> -->
              </fieldset>

              <?php if (!empty($imagepath)): ?>
                <!-- <p class="text-xs text-gray-500">Current image:</p>
                  <img src="../assets/uploads/<?php echo htmlspecialchars($imagepath); ?>" alt="Current"
                    class="w-32 h-32 object-cover rounded border border-gray-300"> -->

                <!-- Store old image path so PHP can keep it if no new file is uploaded -->
                <!-- <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($imagepath); ?>"> -->

                <!-- If image already exists -->
                <div id="fileInfo"
                  class="cursor-pointer flex-col w-full rounded-md bg-white p-4 gap-2 border shadow-md mt-4">
                  <div class="flex justify-between text-sm">
                    <span id="fileName" class="text-gray-700">
                      <?php echo htmlspecialchars(basename($imagepath)); ?>
                    </span>
                    <?php
                    $fileFullPath = "../assets/uploads/" . $imagepath;
                    $sizeMB = file_exists($fileFullPath) ? number_format(filesize($fileFullPath) / (1024 * 1024), 2) . " MB" : "N/A";
                    ?>
                    <span id="fileSize" class="px-2 py-1 shadow-sm bg-white border rounded-md text-gray-500">
                      <?= $sizeMB ?>
                    </span>
                  </div>
                  <div class="flex justify-between">
                    <span id="fileType" class="text-xs bg-gray-200 rounded-md px-1 py-1 text-gray-500">
                      <?= mime_content_type($fileFullPath) ?>
                    </span>
                    <span id="fileDate" class="text-gray-500 text-sm">
                      modified <?= date("m/d/Y", filemtime($fileFullPath)) ?>
                    </span>
                  </div>
                </div>

                <!-- Hidden file input for re-upload -->
                <input type="file" name="image" id="image" accept="image/*" class="hidden" />
                <!-- Keep old image reference -->
                <input type="hidden" name="old_image" value="<?php echo htmlspecialchars($imagepath); ?>">
              <?php else: ?>
                <div class="w-full flex justify-center">
                  <input type="file" name="image" id="image" accept="image/*" class="hidden" />
                  <div id="box" class="relative group w-32 h-32">
                    <!-- Dashed border -->
                    <div class="absolute inset-0 border-2 border-dashed border-blue-400 rounded-md 
                   opacity-0 transition-opacity duration-300 group-hover:opacity-100"></div>

                    <!-- Green box -->
                    <label for="image" class="absolute top-0 right-0 w-32 h-32 border shadow-md bg-white flex justify-center items-center 
                   rounded-md text-zinc-700 cursor-pointer
                   transition-all duration-500 ease-in-out 
                   group-hover:-top-4 group-hover:-right-4">

                      <i data-lucide="upload" class="w-5 h-5"></i>
                    </label>
                  </div>

                </div>
                <!-- File info (hidden by default) -->
                <div id="fileInfo"
                  class="hidden cursor-pointer flex-col w-full rounded-md bg-white p-4 gap-2 border shadow-md mt-4">
                  <div class="flex justify-between text-sm">
                    <span id="fileName" class="text-gray-700"></span>
                    <span id="fileSize" class="px-2 py-1 shadow-sm bg-white border rounded-md text-gray-500"></span>
                  </div>
                  <div class="flex justify-between">
                    <span id="fileType" class="text-xs bg-gray-200 rounded-md px-1 py-1 text-gray-500"></span>
                    <span id="fileDate" class="text-gray-500 text-sm"></span>
                  </div>
                </div>
              <?php endif; ?>






              </fieldset>

            </div>

            <!-- Submit Button -->
            <div class="pt-4">
              <?php if ($submissionid): ?>
                <button type="submit"
                  class="w-full bg-emerald-900 text-white py-2 px-4 rounded-full hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
                  Resubmit Crop
                </button>
                <input type="hidden" name="submissionid" value="<?php echo htmlspecialchars($submissionid); ?>">


              <?php else: ?>
                <button type="submit"
                  class="w-full bg-emerald-900 text-white py-2 px-4 rounded-lg hover:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-colors">
                  Submit Crop
                </button>
              <?php endif; ?>

            </div>
          </form>
        </div>
      </div>
    </div>
  </main>
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

<script>
  const imageInput = document.getElementById("image");
  const fileInfoBox = document.getElementById("fileInfo");
  const fileName = document.getElementById("fileName");
  const box = document.getElementById("box");
  const fileSize = document.getElementById("fileSize");
  const fileType = document.getElementById("fileType");
  const fileDate = document.getElementById("fileDate");

  imageInput.addEventListener("change", () => {
    const file = imageInput.files[0];
    if (file) {
      // Convert size to MB with 2 decimals
      const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
      // Format last modified date
      const lastModified = new Date(file.lastModified).toLocaleDateString();

      fileName.textContent = file.name;
      fileSize.textContent = `${sizeMB} MB`;
      fileType.textContent = file.type || "Unknown type";
      fileDate.textContent = `modified ${lastModified}`;

      // Show the info box
      fileInfoBox.classList.remove("hidden");
      box.classList.add("hidden");
    } else {
      box.classList.remove("hidden");
      fileInfoBox.classList.add("hidden");
    }
  });
  // Allow clicking file info box to re-upload
  fileInfoBox.addEventListener("click", () => {
    imageInput.click();
  });
</script>