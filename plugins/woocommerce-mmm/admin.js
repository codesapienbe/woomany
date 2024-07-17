// admin.js
jQuery(document).ready(function($) {
    // Add any JavaScript needed to enhance the user experience
    // For example, you could add dynamic form validation, interactivity, etc.

    // Example: Simple form validation for adding store hours
    $('form').on('submit', function(event) {
        var dayOfWeek = $('input[name="day_of_week"]').val();
        var openTime = $('input[name="open_time"]').val();
        var closeTime = $('input[name="close_time"]').val();

        if (dayOfWeek === '' || openTime === '' || closeTime === '') {
            alert('Please fill out all fields for store hours.');
            event.preventDefault();
            return false;
        }

        if (parseInt(dayOfWeek) < 1 || parseInt(dayOfWeek) > 7) {
            alert('Please enter a valid day of the week (1-7).');
            event.preventDefault();
            return false;
        }

        return true;
    });
});
