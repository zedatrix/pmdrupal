//Assign jQuery to the standard use since Drupal by default specifies the noConflict mode
//This is just for ease of development.
window.$ = jQuery;
$(document).ready(function(){
    //Get the data passed from the controller and assign it to a local javascript variable for ease of use
    var data = drupalSettings.inbox.data;
    //Populate all the fields of the form with the data from the rest api
    $('#pm_dynaform').find(':input').each(function(){
        //We first check to make sure that the field has a proper id
        //Then we assign to the field's value with the associated field returned from the API
        if ( typeof($(this).attr('id')) !== 'undefined' ) $(this).val(data[$(this).attr('id')]);
    });
    //Instantiate the datepicker widget from jQuery UI - this ships by default with Drupal 8 and is included in inbox.libraries.yml
    $('.date').datepicker();
});