//Assign jQuery to the standard use since Drupal by default specifies the noConflict mode
//This is just for ease of development.
window.$ = jQuery;
//When the DOM is ready, start our javascripts
$(document).ready(function(){
    //This is for when a user clicks on a row, it should open up a case
    $('.pm_table tr').click(function(){
        //Check if the row is the table head, if so, return because we don't want this to run for a table head
        if($(this).attr('id') === 'table_head') return;
        //Change the url based on the process id and app id and delegation index
        window.location.href = 'opencase/'+$(this).attr('prouid')+'/'+$(this).attr('appuid')+'/'+$(this).attr('delindex');
    });
});