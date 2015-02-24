<?php
//Declare the namespace for the controller
namespace Drupal\inbox\Controller;
/**
 * Class HomeController
 * @package Drupal\inbox\Controller
 * We extend the main ProcessMaker Controller
 */
class HomeController extends ProcessmakerController{
    /**
     * @return array
     * Home page for the module
     */
    public function home(){
        //Check if the user is authorized and get the access token
        if($this->isAuthorized()){
            //If the user is authorized, return a message telling the user that they are authenticated
            //Also tell them which workspace they are authenticated in
            return [
                '#theme' => 'inbox_home',
                '#auth' => 'true',
                '#data' => 'You are authenticated in ProcessMaker.',
                '#workspace' => $this->workspace
            ];
        }else{
            //If the user is not authenticated, return a message informing them that they are not authenticated
            return [
                '#theme' => 'inbox_home',
                '#auth' => 'false',
                '#data' => 'There appears to be a problem with your authentication.'
            ];
        }
    }

    /**
     * @return array
     * Function for displaying the new case page
     */
    public function newcase(){
        //Check if the user is authorized and get the access token
        if($this->isAuthorized()){
            //Get a list of all the projects the user can access
            $projects = $this->api_call('/project')->json();
            //Define an empty array for the loop below
            $data = array();
            //Loop through each project to get the associated starting tasks
            foreach($projects as $prj){
                //Get the starting tasks for each process
                $response = $this->api_call('/project/'.$prj['prj_uid'].'/starting-tasks')->json();
                //If there are starting tasks then we assign it to the returned array
                //Not all processes necessarily have starting tasks
                //We create the array starting_tasks for each process and for each process we assign tasks
                //In each tasks array there is a process uid, process name and the array of starting tasks, which contains
                //Their uid and name
                if(sizeof($response)>0)
                    $data['starting_tasks'][] = array('pro_uid' => $prj['prj_uid'], 'prj_name' => $prj['prj_name'], 'starting_tasks' => $response);
            }
            //return array for the theme function, we return the theme for the page, the auth which is true and the array of starting tasks
            return [
                '#theme' => 'inbox_newcase',
                '#auth' => 'true',
                '#data' => $data
            ];
        }
    }

    /**
     * @param $pro_uid - Process uid
     * @param $taskuid - Task uid
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * Function to start a case and open the form
     */
    public function startCase($pro_uid, $taskuid){
        //Check if the user is authorized and get the access token
        if($this->isAuthorized()){
            //Make a call the the api to create a new case
            //The request must be in POST to CREATE a new case
            //Pass the process uid and task uid for the task you are starting
            $data = $this->api_call('/cases',
                'POST',
                json_encode(array('pro_uid' => $pro_uid, 'tas_uid' => $taskuid))
            )->json();
            //Once we have the data, we redirect the use to the opencase route with the params necessary for the route
            return $this->redirect('inbox.opencase', array('prouid' => $pro_uid, 'appuid' => $data['app_uid'], 'delindex' => 1));
        }
    }
}