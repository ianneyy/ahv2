

  const cropButton = document.getElementById('cropButton');
  const cropDropdown = document.getElementById('cropDropdown');
  const cropArrow = document.getElementById('cropArrow');
  const cropSelect = document.querySelector('select[name="croptype"]');


  
  
  cropButton.addEventListener('click', function () {
    
    const isOpen = !cropDropdown.classList.contains('hidden');

    if (isOpen) {
      // Close dropdown
      cropDropdown.classList.add('hidden');
      cropButton.classList.add('bg-white');
      cropButton.classList.remove('bg-green-600');
      cropButton.classList.add('text-gray-600');
      cropButton.classList.remove('text-white');
      cropArrow.classList.remove('rotate-180');
    } else {
      // Open dropdown
      cropDropdown.classList.remove('hidden');
      cropButton.classList.add('bg-green-600');
      cropButton.classList.add('text-white');
      cropButton.classList.remove('text-gray-600');
      cropButton.classList.remove('bg-white');
      cropArrow.classList.add('rotate-180');
    }
  });

 
  // Close Crop dropdown when clicking outside
  document.addEventListener('click', function (event) {
    const isClickInsideDropdown = cropDropdown.contains(event.target);
    const isClickOnSortButton =  cropButton.contains(event.target);

    if (!isClickInsideDropdown && !isClickOnSortButton && !cropDropdown.classList.contains('hidden')) {
      cropDropdown.classList.add('hidden');
      cropButton.classList.remove('bg-green-600');
      cropButton.classList.remove('text-white');
      cropButton.classList.add('bg-white');
      cropButton.classList.add('text-gray-600');
      cropArrow.classList.remove('rotate-180');
    }
  });




    // Add click handlers for crop dropdown options
  const cropDropdownOptions = cropDropdown.querySelectorAll('[data-crop-value]');

  cropDropdownOptions.forEach(option => {
    option.addEventListener('click', function () {
      // Close dropdown after selection
      const cropValue = this.getAttribute('data-crop-value');
      cropSelect.value = cropValue; // update the hidden <select>
      cropSelect.dispatchEvent(new Event('change'));
      cropSelect.form.submit(); 
      cropDropdown.querySelectorAll('[data-crop-value] div').forEach(dot => {
      dot.classList.add('hidden');
    });

     this.querySelector('div').classList.remove('hidden');
      cropDropdown.classList.add('hidden');
      cropButton.classList.remove('bg-green-600');
      cropButton.classList.add('bg-white');
      cropButton.classList.add('text-gray-600');
      cropButton.classList.remove('text-white');
      cropArrow.classList.remove('rotate-180');
      

      // You can add logic here to handle the selected option
      console.log('Selected:', this.textContent.trim());
    });
  });





  // =====================
  // Status dropdown logic
  // =====================
  const statusButton = document.getElementById('statusButton');
  const statusDropdown = document.getElementById('statusDropdown');
  const statusArrow = document.getElementById('statusArrow');
  const statusSelect = document.querySelector('select[name="status"]');

    statusButton.addEventListener('click', function () {
    
    const isOpen = !statusDropdown.classList.contains('hidden');

    if (isOpen) {
      // Close dropdown
      statusDropdown.classList.add('hidden');
      statusButton.classList.add('bg-white');
      statusButton.classList.remove('bg-green-600');
      statusButton.classList.add('text-gray-600');
      statusButton.classList.remove('text-white');
      statusArrow.classList.remove('rotate-180');
    } else {
      // Open dropdown
      statusDropdown.classList.remove('hidden');
      statusButton.classList.add('bg-green-600');
      statusButton.classList.add('text-white');
      statusButton.classList.remove('text-gray-600');
      statusButton.classList.remove('bg-white');
      statusArrow.classList.add('rotate-180');
    }
  });


 document.addEventListener('click', function (event) {
    const isClickInsideDropdown = statusDropdown.contains(event.target);
    const isClickOnSortButton =  statusButton.contains(event.target);

    if (!isClickInsideDropdown && !isClickOnSortButton && !statusDropdown.classList.contains('hidden')) {
      statusDropdown.classList.add('hidden');
      statusButton.classList.remove('bg-green-600');
      statusButton.classList.remove('text-white');
      statusButton.classList.add('bg-white');
      statusButton.classList.add('text-gray-600');
      statusArrow.classList.remove('rotate-180');
    }
  });


    const statusDropdownOptions = statusDropdown.querySelectorAll('[data-status-value]');

  statusDropdownOptions.forEach(option => {
    option.addEventListener('click', function () {
      // Close dropdown after selection
      const statusValue = this.getAttribute('data-status-value');
      statusSelect.value = statusValue; // update the hidden <select>
      statusSelect.dispatchEvent(new Event('change'));
      statusSelect.form.submit(); 

      statusDropdown.querySelectorAll('[data-status-value] div').forEach(dot => {
      dot.classList.add('hidden');
    });

     this.querySelector('div').classList.remove('hidden');
      statusDropdown.classList.add('hidden');
      statusButton.classList.remove('bg-green-600');
      statusButton.classList.add('bg-white');
      statusButton.classList.add('text-gray-600');
      statusButton.classList.remove('text-white');
      statusArrow.classList.remove('rotate-180');
      

      // You can add logic here to handle the selected option
      console.log('Selected:', this.textContent.trim());
    });
  });