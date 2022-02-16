<?php 

defined( 'ABSPATH' ) || exit;

class Google_Drive_Helper {

    private $client_id = 'XXXXX';

    private $client_secret = 'XXXX';  

    const ROOT_FOLDER = FCPATH . '/application/libraries/google';

    const APPLICATION_NAME = 'YOUR DRIVE APP NAME';

    public function __construct(){

        $this->client = $this->get_client();

    } 

    /**
     * Returns an authorized API client.
     * @return Google_Client the authorized client object
     */
    public function get_client()
    {
        $client = new Google_Client();
        $client->setApplicationName( self::APPLICATION_NAME );
        $client->setScopes(Google_Service_Drive::DRIVE);
        $client->setAuthConfig( self::ROOT_FOLDER . '/includes/google-credentials/drive/credentials.json');
        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');

        // Load previously authorized token from a file, if it exists.
        // The file token.json stores the user's access and refresh tokens, and is
        // created automatically when the authorization flow completes for the first
        // time.
        $tokenPath = self::ROOT_FOLDER . '/includes/google-credentials/drive/token.json';
        if (file_exists($tokenPath)) {
            $accessToken = json_decode(file_get_contents($tokenPath), true);
            $client->setAccessToken($accessToken);
        }

        // If there is no previous token or it's expired.
        if ($client->isAccessTokenExpired()) {
            // Refresh the token if possible, else fetch a new one.
            if ($client->getRefreshToken()) {
                $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            } else {
                // Request authorization from the user.
                $authUrl = $client->createAuthUrl();
                printf("Open the following link in your browser:\n%s\n", $authUrl);
                print 'Enter verification code: ';
                $authCode = trim(fgets(STDIN));

                // Exchange authorization code for an access token.
                $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);
                $client->setAccessToken($accessToken);

                // Check to see if there was an error.
                if (array_key_exists('error', $accessToken)) {
                    throw new Exception(join(', ', $accessToken));
                }
            }
            // Save the token to a file.
            if (!file_exists(dirname($tokenPath))) {
                mkdir(dirname($tokenPath), 0700, true);
            }
            file_put_contents($tokenPath, json_encode($client->getAccessToken()));
        }
        return $client;
    }

    // This will create a folder and also sub folder when $parent_folder_id is given
    function create_folder( $folder_name, $parent_folder_id = null ){

        $folder_list = $this->check_folder_exists( $folder_name, $parent_folder_id );

        // if folder does not exists
        if( count( $folder_list ) == 0 ){
            $service = new Google_Service_Drive( $this->client );
            $folder = new Google_Service_Drive_DriveFile();
        
            $folder->setName( $folder_name );
            $folder->setMimeType('application/vnd.google-apps.folder');
            if( !empty( $parent_folder_id ) ){
                $folder->setParents( [ $parent_folder_id ] );        
            }

            $result = $service->files->create( $folder );
        
            $folder_id = null;
            
            if( isset( $result['id'] ) && !empty( $result['id'] ) ){
                $folder_id = $result['id'];
            }
        
            return $folder_id;
        }

        return $folder_list[0]['id'];
        
    }

    // This will check folders and sub folders by name
    function check_folder_exists( $folder_name, $parent_folder_id = null ){
        
        $service = new Google_Service_Drive($this->client);

        if ( $parent_folder_id == null ) {
            $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and name='$folder_name' and trashed=false";
        }else{
            $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and name='$folder_name' and parents in '$parent_folder_id' and trashed=false";
        } 
        $files = $service->files->listFiles($parameters);

        $op = [];
        foreach( $files as $k => $file ){
            $op[] = $file;
        }

        return $op;
    } 

    // This will check folders and sub folders by id
    function check_folder_exists_by_id( $folder_id, $parent_folder_id = null ){
        
        $service = new Google_Service_Drive($this->client);

        if ( $parent_folder_id == null ) {
            $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and id='$folder_id' and trashed=false";
        }else{
            $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and id='$folder_id' and parents in '$parent_folder_id' and trashed=false";
        } 
        $files = $service->files->listFiles($parameters);

        $op = [];
        foreach( $files as $k => $file ){
            $op[] = $file;
        }

        return $op;
    }

    // This will display list of folders and direct child folders and files.
    function get_files_and_folders(){
        $service = new Google_Service_Drive($this->client);

        $parameters['q'] = "mimeType='application/vnd.google-apps.folder' and 'root' in parents and trashed=false";
        $files = $service->files->listFiles($parameters);
        
        echo "<ul>";
        foreach( $files as $k => $file ){
            echo "<li> 
            
                {$file['name']} - {$file['id']} ---- ".$file['mimeType'];

                try {
                    // subfiles
                    $sub_files = $service->files->listFiles(array('q' => "'{$file['id']}' in parents"));
                    echo "<ul>";
                    foreach( $sub_files as $kk => $sub_file ) {
                        echo "<li&gt {$sub_file['name']} - {$sub_file['id']}  ---- ". $sub_file['mimeType'] ." </li>";
                    }
                    echo "</ul>";
                } catch (\Throwable $th) {
                    // dd($th);
                }
            
            echo "</li>";
        }
        echo "</ul>";
    }

    // This will insert file into drive and returns boolean values.
    function insert_file_to_drive( $file_path, $file_name, $parent_file_id = null ){
        $service = new Google_Service_Drive( $this->client );
        $file = new Google_Service_Drive_DriveFile();

        $file->setName( $file_name );

        if( !empty( $parent_file_id ) ){
            $file->setParents( [ $parent_file_id ] );        
        }

        $result = $service->files->create(
            $file,
            array(
                'data' => file_get_contents($file_path),
                'mimeType' => 'application/octet-stream',
            )
        );

        $is_success = false;
        
        if( isset( $result['name'] ) && !empty( $result['name'] ) ){
            $is_success = true;
        }

        return $is_success;
    } 

    function get_folder_by_name( $folder_name ){

        $service = new Google_Service_Drive( $this->client );
        $optParams = array( 'q' => "mimeType='application/vnd.google-apps.folder' and name='".$folder_name."' and trashed=false" ); 
        $files_list = $service->files->listFiles($optParams); 

        if ( count( $files_list ) >= 1 ) {
            return $files_list->files[0];
        }else{
            return false;
        }

    }

    // Function just for easier debugging
    function dd( ...$d ){
        echo "<pre style='background-color:#000;color:#fff;' >";
        print_r($d);
        exit;
    }      

}
