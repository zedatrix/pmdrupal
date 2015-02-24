<?php
//Declare the namespace for the controller
namespace Drupal\inbox\Controller;
/**
 * Class FormController
 * @package Drupal\inbox\Controller
 * This class is the controller for the forms in the module
 * We extend the main ProcessMaker Controller
 */
class FormController extends ProcessmakerController{
    /**
     * @param $prouid - Process UID
     * @param $appuid - Application UID
     * @param $delindex - Delegation Index
     * @return array
     * This function is called when the form is requested
     */
    public function displayPage($prouid, $appuid, $delindex){
        //Check if the user is authorized and get the access token
        if($this->isAuthorized()){
            //Assign the controller properties
            $this->prouid = $prouid;
            $this->appuid = $appuid;
            $this->delindex = $delindex;
            //Make the call to the api to get the application meta data of the case
            $app_data = $this->api_call('/cases/'.$this->appuid)->json();
            //Assign the app number and task uid to the class
            $this->appnumber = $app_data['app_number'];
            $this->taskuid = $app_data['current_task'][0]['tas_uid'];
            //Make a call to the api to get the list of steps for the current task
            $steps = $this->api_call('/project/'.$this->prouid.'/activity/'.$this->taskuid.'/steps')->json();
            //Make a call to the api to get the dynaform to display
            $dynaform = $this->api_call('/project/'.$this->prouid.'/dynaform/'.$steps[0]['step_uid_obj'])->json();
            //Extract the dynaform definition from the api call response
            $dynaform_deff = json_decode($dynaform['dyn_content']);
            //Get the list of fields
            $fields = $dynaform_deff->items[0]->items;
            //Create a new routing object
            $route_match = \Drupal::routeMatch();
            //Get the current routes path
            $route_path = $route_match->getRouteObject()->getPath();
            //Explode the path to get the current state
            $routes = explode('/', $route_path);
            //Switch for displaying the form
            switch($routes[2]){
                case 'opencase':
                    //Get the applications data
                    $data = $this->loadDataOnForm();
                    //Assign some meta data of the case
                    $data['appuid'] = $this->appuid;
                    $data['prouid'] = $this->prouid;
                    $data['delindex'] = $this->delindex;
                    //Return the array for the theme function with all the data for the form
                    return [
                        '#theme' => 'inbox_form',
                        '#auth' => 'true',
                        '#title' => $dynaform['dyn_title'],
                        '#fields' => $fields,
                        '#submit' => $fields[sizeof($fields)-1][0],
                        '#app_number' => $this->appnumber,
                        '#attached' => array('drupalSettings' => array('inbox' => array('data' => $data, 'debug' => array('fields' => $fields))))
                    ];
                break;
                default:
                break;
            }

        }
    }

    /**
     * @return mixed
     * This function is a wrapper to get the data from the api
     * It was created to allow a developer to extend the data being returned in case of needing to filter and modify the response data
     */
    public function loadDataOnForm(){
        //Make a call to the api to get the data and return it
        return $this->api_call('/cases/'.$this->appuid.'/variables')->json();
    }

    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * This function saves the data from the form in ProcessMaker and routes the case
     */
    public function saveForm(){
        //Assign the class properties the correct values
        $this->appuid = $_POST['appuid'];
        $this->prouid = $_POST['prouid'];
        $this->delindex = $_POST['delindex'];
        //Delete the 3 meta data fields so that we can just send the full post to ProcessMaker
        unset($_POST['appuid']);
        unset($_POST['prouid']);
        unset($_POST['delindex']);
        //Make the call to the api to save the data in ProcessMaker
        //The object being sent must be a json object, that is why we json_encode it
        $save = $this->api_call('/cases/'.$this->appuid.'/variable',
            'PUT',
            $data = json_encode($_POST)
        )->json();
        //If the save response returns a non 0 value, we know it was successful and we can the route the case
        if($save !== 0){
            //Make a call to the api to route the case
            $this->api_call('/cases/'.$this->appuid.'/route-case',
                'PUT'
            );
            //After the routing, return the user to the inbox
            return $this->redirect('inbox.inbox');
        }else{
            //If the case was not successful in saving it, we return the user back to their form so that they know there is an issue
            //ToDo return a message to the user informing them that their save was unsuccessful
            return $this->redirect('inbox.opencase', array('prouid' => $this->prouid, 'appuid' => $this->appuid, 'delindex' => $this->delindex));
        }
    }
}