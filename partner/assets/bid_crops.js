  const sortButton = document.getElementById('sortButton');
  const sortDropdown = document.getElementById('sortDropdown');
  const sortArrow = document.getElementById('sortArrow');
  const sortSelect = document.querySelector('select[name="sort"]');

  const cropButton = document.getElementById('cropButton');
  const crop = document.getElementById('crop');
  const cropDropdown = document.getElementById('cropDropdown');
  const cropArrow = document.getElementById('cropArrow');
  const cropSelect = document.querySelector('select[name="croptype"]');


  sortButton.addEventListener('click', function () {
    
    const isOpen = !sortDropdown.classList.contains('hidden');

    if (isOpen) {
      // Close dropdown
      sortDropdown.classList.add('hidden');
      sortButton.classList.add('bg-white');
      sortButton.classList.remove('bg-emerald-600');
      sortButton.classList.add('text-gray-600');
      sortButton.classList.remove('text-white');
      sortArrow.classList.remove('rotate-180');
    } else {
      // Open dropdown
      sortDropdown.classList.remove('hidden');
      sortButton.classList.add('bg-emerald-600');
      sortButton.classList.add('text-white');
      sortButton.classList.remove('text-gray-600');

      sortButton.classList.remove('bg-white');
      sortArrow.classList.add('rotate-180');
    }
  });
  
  cropButton.addEventListener('click', function () {
    
    const isOpen = !cropDropdown.classList.contains('hidden');

    if (isOpen) {
      // Close dropdown
      cropDropdown.classList.add('hidden');
      cropButton.classList.add('bg-white');
      cropButton.classList.remove('bg-emerald-600');
      cropButton.classList.add('text-gray-600');
      cropButton.classList.remove('text-white');
      cropArrow.classList.remove('rotate-180');
    } else {
      // Open dropdown
      cropDropdown.classList.remove('hidden');
      cropButton.classList.add('bg-emerald-600');
      cropButton.classList.add('text-white');
      cropButton.classList.remove('text-gray-600');
      cropButton.classList.remove('bg-white');
      cropArrow.classList.add('rotate-180');
    }
  });

  // Close dropdown when clicking outside
  document.addEventListener('click', function (event) {
    const isClickInsideDropdown = sortDropdown.contains(event.target);
    const isClickOnSortButton = sortButton.contains(event.target);

    if (!isClickInsideDropdown && !isClickOnSortButton && !sortDropdown.classList.contains('hidden')) {
      sortDropdown.classList.add('hidden');
      sortButton.classList.remove('bg-emerald-600');
      sortButton.classList.remove('text-white');
      sortButton.classList.add('bg-white');
      sortButton.classList.add('text-gray-600');
      sortArrow.classList.remove('rotate-180');
    }
  });

  // Close Crop dropdown when clicking outside
  document.addEventListener('click', function (event) {
    const isClickInsideDropdown = cropDropdown.contains(event.target);
    const isClickOnSortButton =  cropButton.contains(event.target);

    if (!isClickInsideDropdown && !isClickOnSortButton && !cropDropdown.classList.contains('hidden')) {
      cropDropdown.classList.add('hidden');
      cropButton.classList.remove('bg-emerald-600');
      cropButton.classList.remove('text-white');
      cropButton.classList.add('bg-white');
      cropButton.classList.add('text-gray-600');
      cropArrow.classList.remove('rotate-180');
    }
  });

  // Add click handlers for dropdown options
  const dropdownOptions = sortDropdown.querySelectorAll('[data-sort-value]');
  dropdownOptions.forEach(option => {
    option.addEventListener('click', function () {
      // Close dropdown after selection
      const value = this.getAttribute('data-sort-value');
      sortSelect.value = value; // update the hidden <select>
      sortSelect.form.submit(); 

      sortDropdown.querySelectorAll('[data-sort-value] div').forEach(dot => {
      dot.classList.add('hidden');
    });

     this.querySelector('div').classList.remove('hidden');
      sortDropdown.classList.add('hidden');
      sortButton.classList.remove('bg-emerald-600');
      sortButton.classList.add('bg-white');
      sortButton.classList.add('text-gray-600');
      sortButton.classList.remove('text-white');
      sortArrow.classList.remove('rotate-180');
      

      // You can add logic here to handle the selected option
      console.log('Selected:', this.textContent.trim());
    });
  });


    // Add click handlers for crop dropdown options
  const cropDropdownOptions = cropDropdown.querySelectorAll('[data-crop-value]');

  cropDropdownOptions.forEach(option => {
    option.addEventListener('click', function (e) {
      e.preventDefault();
      // Close dropdown after selection
      const cropValue = this.getAttribute('data-crop-value');
      cropSelect.value = cropValue; // update the hidden <select>
      crop.textContent = cropValue;
      cropSelect.dispatchEvent(new Event('change'));
      cropSelect.form.submit(); 


      cropDropdown.querySelectorAll('[data-crop-value] div').forEach(dot => {
      dot.classList.add('hidden');
    });

     this.querySelector('div').classList.remove('hidden');
      cropDropdown.classList.add('hidden');
      cropButton.classList.remove('bg-emerald-600');
      cropButton.classList.add('bg-white');
      cropButton.classList.add('text-gray-600');
      cropButton.classList.remove('text-white');
      cropArrow.classList.remove('rotate-180');
      

      // You can add logic here to handle the selected option
      console.log('Selected:', this.textContent.trim());
    });
  });




  const biddingStatusButton = document.getElementById('biddingStatusButton');
  const biddingStatusDropdown = document.getElementById('biddingStatusDropdown');
  const biddingStatusArrow = document.getElementById('biddingStatusArrow');
  const biddingStatusSelect = document.querySelector('select[name="status"]');



biddingStatusButton.addEventListener('click', function () {
    
    const isOpen = !biddingStatusDropdown.classList.contains('hidden');

    if (isOpen) {
      // Close dropdown
      biddingStatusDropdown.classList.add('hidden');
      biddingStatusButton.classList.add('bg-white');
      biddingStatusButton.classList.remove('bg-emerald-600');
      biddingStatusButton.classList.add('text-gray-600');
      biddingStatusButton.classList.remove('text-white');
      biddingStatusArrow.classList.remove('rotate-180');
    } else {
      // Open dropdown
      biddingStatusDropdown.classList.remove('hidden');
      biddingStatusButton.classList.add('bg-emerald-600');
      biddingStatusButton.classList.add('text-white');
      biddingStatusButton.classList.remove('text-gray-600');

      biddingStatusButton.classList.remove('bg-white');
      biddingStatusArrow.classList.add('rotate-180');
    }
  });

  document.addEventListener('click', function (event) {
    const isClickInsideDropdown = biddingStatusDropdown.contains(event.target);
    const isClickOnSBiddingStatusButton = biddingStatusButton.contains(event.target);

    if (!isClickInsideDropdown && !isClickOnSBiddingStatusButton && !biddingStatusDropdown.classList.contains('hidden')) {
      biddingStatusDropdown.classList.add('hidden');
      biddingStatusButton.classList.remove('bg-emerald-600');
      biddingStatusButton.classList.remove('text-white');
      biddingStatusButton.classList.add('bg-white');
      biddingStatusButton.classList.add('text-gray-600');
      biddingStatusArrow.classList.remove('rotate-180');
    }
  });

  const biddingStatusDropdownOptions = biddingStatusDropdown.querySelectorAll('[data-biddingStatus-value]');
  biddingStatusDropdownOptions.forEach(option => {
    option.addEventListener('click', function () {
      // Close dropdown after selection
      const value = this.getAttribute('data-biddingStatus-value');
      biddingStatusSelect.value = value; // update the hidden <select>
      biddingStatusSelect.form.submit(); 
      biddingStatusDropdown.querySelectorAll('[data-biddingStatus-value] div').forEach(dot => {
      dot.classList.add('hidden');
    });

     this.querySelector('div').classList.remove('hidden');
      biddingStatusDropdown.classList.add('hidden');
      biddingStatusButton.classList.remove('bg-emerald-600');
      biddingStatusButton.classList.add('bg-white');
      biddingStatusButton.classList.add('text-gray-600');
      biddingStatusButton.classList.remove('text-white');
      biddingStatusArrow.classList.remove('rotate-180');
      

      // You can add logic here to handle the selected option
      console.log('Selected:', this.textContent.trim());
    });
  });





    const yourStatusButton = document.getElementById('yourStatusButton');
  const yourStatusDropdown = document.getElementById('yourStatusDropdown');
  const yourStatusArrow = document.getElementById('yourStatusArrow');
  const yourStatusSelect = document.querySelector('select[name="userstatus"]');



yourStatusButton.addEventListener('click', function () {
    
    const isOpen = !yourStatusDropdown.classList.contains('hidden');

    if (isOpen) {
      // Close dropdown
      yourStatusDropdown.classList.add('hidden');
      yourStatusButton.classList.add('bg-white');
      yourStatusButton.classList.remove('bg-emerald-600');
      yourStatusButton.classList.add('text-gray-600');
      yourStatusButton.classList.remove('text-white');
      yourStatusArrow.classList.remove('rotate-180');
    } else {
      // Open dropdown
      yourStatusDropdown.classList.remove('hidden');
      yourStatusButton.classList.add('bg-emerald-600');
      yourStatusButton.classList.add('text-white');
      yourStatusButton.classList.remove('text-gray-600');

      yourStatusButton.classList.remove('bg-white');
      yourStatusArrow.classList.add('rotate-180');
    }
  });

  document.addEventListener('click', function (event) {
    const isClickInsideDropdown = yourStatusDropdown.contains(event.target);
    const isClickOnSYourStatusButton = yourStatusButton.contains(event.target);

    if (!isClickInsideDropdown && !isClickOnSYourStatusButton && !yourStatusDropdown.classList.contains('hidden')) {
      yourStatusDropdown.classList.add('hidden');
      yourStatusButton.classList.remove('text-white');
      yourStatusButton.classList.add('bg-white');
      yourStatusButton.classList.add('text-gray-600');
      yourStatusButton.classList.remove('bg-emerald-600');
      biddingStatusArrow.classList.remove('rotate-180');
    }
  });

  const yourStatusDropdownOptions = yourStatusDropdown.querySelectorAll('[data-yourStatus-value]');
  yourStatusDropdownOptions.forEach(option => {
    option.addEventListener('click', function () {
      // Close dropdown after selection
      const value = this.getAttribute('data-yourStatus-value');
      yourStatusSelect.value = value; // update the hidden <select>
      yourStatusSelect.form.submit(); 

      yourStatusDropdown.querySelectorAll('[data-yourStatus-value] div').forEach(dot => {
      dot.classList.add('hidden');
    });

     this.querySelector('div').classList.remove('hidden');
      yourStatusDropdown.classList.add('hidden');
      yourStatusButton.classList.remove('bg-emerald-600');
      yourStatusButton.classList.add('bg-white');
      yourStatusButton.classList.add('text-gray-600');
      yourStatusButton.classList.remove('text-white');
      yourStatusArrow.classList.remove('rotate-180');
      

      // You can add logic here to handle the selected option
      console.log('Selected:', this.textContent.trim());
    });
  });