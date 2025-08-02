// Bulma navbar burger toggle
// This script enables the burger menu for mobile navigation

document.addEventListener('DOMContentLoaded', () => {
  const $navbarBurgers = Array.prototype.slice.call(document.querySelectorAll('.navbar-burger'), 0);
  if ($navbarBurgers.length > 0) {
    $navbarBurgers.forEach( el => {
      el.addEventListener('click', () => {
        const target = el.dataset.target;
        const $target = document.getElementById(target);
        el.classList.toggle('is-active');
        $target.classList.toggle('is-active');
      });
    });
  }

  // Enhanced dropdown functionality
  const dropdowns = document.querySelectorAll('.dropdown');
  dropdowns.forEach(dropdown => {
    const trigger = dropdown.querySelector('.dropdown-trigger button');
    const menu = dropdown.querySelector('.dropdown-menu');
    
    // Ensure dropdown shows on hover
    dropdown.addEventListener('mouseenter', () => {
      dropdown.classList.add('is-active');
    });
    
    // Hide dropdown when mouse leaves
    dropdown.addEventListener('mouseleave', () => {
      dropdown.classList.remove('is-active');
    });
    
    // Toggle dropdown on click for mobile/touch devices
    if (trigger) {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        dropdown.classList.toggle('is-active');
      });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      if (!dropdown.contains(e.target)) {
        dropdown.classList.remove('is-active');
      }
    });
  });
});
