<?php
//Declare the namespace for the controller
namespace Drupal\inbox\Controller;
/**
 * Class SearchController
 * @package Drupal\inbox\Controller
 * We extend the main ProcessMaker Controller
 * ToDo Add the functionality
 */
class SearchController extends ProcessmakerController{
    /**
     * @return array
     * The main function called to display the page
     */
    public function displayPage(){
        //Check if the user is authorized and get the access token
        if($this->isAuthorized()) {
            //Get the list of processes to populate the process dropdown
            $data['projects'] = $this->api_call('/projects')->json();
            //Get the list of users to populate the users dropdown
            $data['users'] = $this->api_call('/users')->json();
            //Create the $query variable for use in the loop below
            $query = '';
            //Loop through the post data from the search form
            foreach ($_POST as $param => $value) {
                //Check that the value of the field on the search form actually has data in it
                if ($value != '' && $value != '0') {
                    //If it does have data, built it into the search query
                    $query .= "$param=$value&";
                }
            }
            //Take out the extra & at the end
            $query = substr($query, 0, -1);
            //Get the list of cases from the api based on the search query params
            $data['cases'] = $this->api_call('/cases/advanced-search?' . $query)->json();

            //return array for the theme function, we return the theme for the page, the auth which is true and the data returned from the api
            return [
                '#theme' => 'inbox_search',
                '#auth' => 'true',
                '#data' => $data
            ];
        }
    }

}