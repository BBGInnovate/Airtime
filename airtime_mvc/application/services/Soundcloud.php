<?php
/**
* Application_Service_Soundcloud
* Interact with SoundCloud, requires mptre's PHP Soundcloud Wrapper 
* @category Services
*/

require_once 'soundcloud-api/Services/Soundcloud.php';

class Application_Service_Soundcloud
{
    private $_soundcloud;

    public function __construct()
    {
        $CC_CONFIG = Config::getConfig();
        $this->_soundcloud = new Services_Soundcloud(
            $CC_CONFIG['soundcloud-client-id'],
            $CC_CONFIG['soundcloud-client-secret']);
    }


    /**
     * array of Soundcloud track fields mapped to airtime metadata cols
     */
    var $_scMD = array (
        "id"            => "id",
        "title"         => "track_title",
        "description"   => "description",
        "genre"         => "genre",
        "tag_list"      => "mood",
        "label_name"    => "album_title",
        "release_year"  => "year",
        "permalink_url" => "info_url",
        "isrc"          => "isrc_number",
        "license"       => "copyright",
        "bpm"           => "bpm",
    );

    public function getTempDirectory()
    {
        $tmp = ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "soundcloud" . DIRECTORY_SEPARATOR;
        if (!file_exists($tmp)){
            @mkdir($tmp, 0775, true);
        }
        return $tmp;
    }

    public function getscMD()
    {
        return $this->$_scMD;
    }


    private function getToken()
    {
        if(Application_Model_Preference::GetUploadToSoundcloudOption()){
            $username = Application_Model_Preference::GetSoundCloudUser();
            $password = Application_Model_Preference::GetSoundCloudPassword();

            $token = $this->_soundcloud->accessTokenResourceOwner($username, $password);
            return $token;
        } else {
            return FALSE;
        }
    }

    public function uploadTrack($filepath, $filename, $description,
        $tags=array(), $release=null, $genre=null)
    {                            

        if (!$this->getToken()) {
            throw new NoSoundCloundToken();
        } 
        if (count($tags)) {
            $tags = join(" ", $tags);
            $tags = $tags." ".Application_Model_Preference::GetSoundCloudTags();
        } else {
            $tags = Application_Model_Preference::GetSoundCloudTags();
        }

        $downloadable = Application_Model_Preference::GetSoundCloudDownloadbleOption() == '1';

        $track_data = array(
            'track[sharing]'      => 'private',
            'track[title]'        => $filename,
            'track[asset_data]'   => '@' . $filepath,
            'track[tag_list]'     => $tags,
            'track[description]'  => $description,
            'track[downloadable]' => $downloadable,

        );

        if (isset($release)) {
            $release = str_replace(" ", "-", $release);
            $release = str_replace(":", "-", $release);

            //YYYY-MM-DD-HH-mm-SS
            $release = explode("-", $release);
            $track_data['track[release_year]']  = $release[0];
            $track_data['track[release_month]'] = $release[1];
            $track_data['track[release_day]']   = $release[2];
        }

        if (isset($genre) && $genre != "") {
            $track_data['track[genre]'] = $genre;
        } else {
            $default_genre = Application_Model_Preference::GetSoundCloudGenre();
            if ($default_genre != "") {
                $track_data['track[genre]'] = $default_genre;
            }
        }

        $track_type = Application_Model_Preference::GetSoundCloudTrackType();
        if ($track_type != "") {
            $track_data['track[track_type]'] = $track_type;
        }

        $license = Application_Model_Preference::GetSoundCloudLicense();
        if ($license != "") {
            $track_data['track[license]'] = $license;
        }

        $response = json_decode(
            $this->_soundcloud->post('tracks', $track_data),
            true
        );

        return $response;

    }


    public static function uploadSoundcloud($id) 
    {
        $cmd = "/usr/lib/airtime/utils/soundcloud-uploader $id > /dev/null &";
        Logging::info("Uploading soundcloud with command: $cmd");
        exec($cmd);
    }


    /**
    * Get tracks from SoundCloud.
    * @param $options Soundcloud API "get" options
    * @return request
    *
    */
    public function getTracks($options = null)
    {
        //TODO create a similar "getSets" function
        try{
            if ($this->getToken()) {

                if(!$options){
                    $options = array();
                }

                if(empty($options['user_id'])){
                    $user = $this->getUser();
                    $options['user_id'] = $user['id'];
                }

                $response = json_decode(
                    $this->_soundcloud->get('tracks', $options),
                    true
                );

                return $response;

            } else {

                throw new Exception("Unable to retrieve tracks from SoundCloud. Please Check your SoundCloud preferences.");
            }

        } catch(Services_Soundcloud_Invalid_Http_Response_Code_Exception $e){

            return array("code"=>$e->getHttpCode, "error"=>$e->getHttpBody);
        }
    }


    /**
    * Get a user's Groups (with tracks) from SoundCloud.
    * @param $options Soundcloud API "get" options
    * @return request
    *
    */
    public function getGroups($options = null)
    {
        try{
            if ($this->getToken()) {

                if(!$options){
                    $options = array();
                }

                if(empty($options['user_id'])){
                    $user = $this->getUser();
                    $options['user_id'] = $user['id'];
                }

                $groups = json_decode(
                    $this->_soundcloud->get('groups', $options),
                    true
                );

                foreach ($groups as $index => $group) {
                    if($group['track_count'] > 0 && $group['creator']['id'] == $options['user_id']){
                        //if the current user owns this group add the group tracks to to the response
                        $options['group'] = $group['id']; 
                        $groups[$index]['tracks'] = json_decode($this->_soundcloud->get('groups/' . $group['id'] . '/tracks'), true);
                    }
                }

                return $groups;

            } else {

                throw new Exception("Unable to retrieve tracks from SoundCloud. Please Check your SoundCloud preferences.");
            }

        } catch(Services_Soundcloud_Invalid_Http_Response_Code_Exception $e){

            return array("code"=>$e->getHttpCode, "error"=>$e->getHttpBody);
        }
    }


    /**
    * Get id's of files that have been imported from Soundcloud.
    * @return array of 
    *
    */
    public function getImportedFileIds()
    {
            try {
            $con = Propel::getConnection();

            $sql = <<<SQL
SELECT soundcloud_id AS id
FROM CC_FILES
WHERE (id != -2
       AND id != -3)
    AND soundcloud_id IS NOT NULL
SQL;
            $rows = $con->query($sql)->fetchAll();

            //clean up rows
            $ids  = array();
            foreach($rows as $row){
                if($row['id'])
                    array_push($ids, $row['id']);
            }

            return $ids;

        } catch (Exception $e) {
            header('HTTP/1.0 503 Service Unavailable');
            Logging::info("Could not connect to database.");
            exit;
        }


    }


    /**
    * Get user profile data from SoundCloud.
    * @return request
    */

    public function getUser()
    {
        //TODO add param to allow any user info to be requested by name or id?? -erp
        try{
            if ($this->getToken()) {
                $response = json_decode(
                    $this->_soundcloud->get('me'),
                    true
                );
                return $response;
            } else {
                throw new Exception("Unable to retrieve user from SoundCloud. Please Check your SoundCloud preferences.");
            }

        } catch(Services_Soundcloud_Invalid_Http_Response_Code_Exception $e){
            return array("code"=>$e->getHttpCode, "error"=>$e->getHttpBody);
        }
    }


    /**
    * Import a track from SoundCloud.
    * @param soundcloud track id
    */
    public function importTrack($trackId)
    {
        try {
          $token = $this->getToken();

          if ($token && Application_Model_Preference::GetUploadToSoundcloudOption()) {
            //get track metadata
            $track = $this->getTracks(array('ids' => $trackId));

            $success = false;
            $params = array('oauth_token' => $token['access_token']);

            //soundcloud redirects to the final url of the media asset
            $downloadUrl = 'https://api.soundcloud.com/tracks/' . $trackId . '/download?oauth_token=' . $token['access_token'];

           //create a unique temp filename based on SC id
            $tmpTitle = 'SoundCloud_Track_' . $trackId;
            $tmpFilename = $tmpTitle . '.mp3';
            //get the temp dir
            $destination = $this->getTempDirectory();
            //download the file
            $curlOptions = array('CURLOPT_FOLLOWLOCATION' => 1, 'CURLOPT_HEADER' => 1);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $downloadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            //need to "follow location" because SoundCloud API returns a redirect
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            $data = curl_exec($ch);
            Logging::info("Import soundcloud file: $tmpFilename");
            $file = fopen($destination . $tmpFilename, "w+");
            fputs($file, $data);
            fclose($file);
            $curl_info = curl_getinfo($ch);
            curl_close ($ch);

            if($data == true && file_exists($destination . $tmpFilename)){
                //sucessfully downloaded the media file and moved to tmp dir

                //create the .metadata file
                $mdFile = $this->createMetadataFile($tmpTitle, $trackId);

                $info = array();
                $info['filename'] = $tmpTitle;
                $info['metadata'] = $mdFile;
                $message = "";
                //Copy media file to watch folder
                $copy = Application_Model_StoredFile::copyFileToStor($destination, $tmpFilename, $tmpFilename);

                if(is_null($copy)){
                    $success = true;
                    $response = array('success' => $success, 'message' => "Copied file to watch folder.", 'info' => $info);

                    //mark the imported file with Soundcloud link and id

                } else {
                    //fail to move to watch folder
                    $info['error'] = $copy;
                    $response = array('success' => false, 'message' => "Failed to copy.", 'info' => $info);
                }

            } else {
              //cURL failed or unable to write to tmp directory
              $response = array('success' => false, 'error' => curl_error($ch), 'info' => $info);

            }

          } else {    

            throw new Exception("Error. Unable to import SouncCloud track, Please Check your SoundCloud preferences.");
          }

          return $response;

        } catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
            Logging::info($e->getMessage());
        }
    }

    /**
    * Create a .metadata file of a track imported from SoundCloud.
    * .metadata file contains a newline for each metadata item. e.g. "key=value"
    * @param $filename string
    * @param $trackId int Soundcloud track ID
    */
    public function createMetadataFile($filename, $trackId)
    {
        try {
          $token = $this->getToken();

          if ($token && Application_Model_Preference::GetUploadToSoundcloudOption()) {
            $track = array_shift( json_decode( $this->_soundcloud->get('tracks', array('ids' => $trackId)), true ) );
            if($track['id'] == $trackId){
                $mdFilename = "$filename.metadata";
                $destination = $this->getTempDirectory();
                $filepath = $destination . $mdFilename;
                $md = array();
                if(is_array($track)){
                    foreach($this->_scMD as $scField => $airtimeField){
                        //populate $content array with $track from soundcloud
                        if(array_key_exists($scField, $track) && isset($track[$scField])) {
                            $md[$airtimeField] = $track[$scField];
                        }
                        //set the user 
                        if(isset($track['user']['username'])){
                            $md['artist_name'] = $track['user']['username'];
                        }
                    }
                    //$content = var_export($md, true);
                    $content = "";
                    foreach($md as $key => $value){
                        if(!empty($md[$key])){
                            //strip newlines
                            $cleanVal = trim(preg_replace('/\s\s+/', ' ', $value));
                            $content .= "$key=$cleanVal\n";
                        }
                    }
                    //mark that this file is from SC
                    $content .= "created_with=soundcloud\n";

                    //create metadata file in mp dir
                    $create = file_put_contents($filepath, $content);
                    //move to stor
                    $copy = Application_Model_StoredFile::copyFileToStor($destination, $mdFilename, $mdFilename);
                    if(is_null($copy)){
                        Logging::info("Successfully written metadata file'$mdFilename'");
                        return TRUE;
                    } else {
                        return FALSE;
                    }

                }
            }

          } else {    
            throw new Exception("Error. Unable to import SouncCloud track: " .  $trackId . ", Please Check your SoundCloud preferences.");
          }

          return FALSE;

        } catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
            Logging::info($e->getMessage());
        }

    }


    /**
    * Delete a track from SoundCloud.
    * @param soundcloud track id
    */
    public function deleteTrack($trackId)
    {
        try {
          $token = $this->getToken();

          if ($token && Application_Model_Preference::GetUploadToSoundcloudOption()) {

            $response = json_decode( $this->_soundcloud->delete('tracks/' . $trackId), true );

            return $response;

          } else {    
            throw new Exception("Error. Unable to import SouncCloud track: " .  $trackId . ", Please Check your SoundCloud preferences.");
          }

          return $response;

        } catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
            Logging::info($e->getMessage());
        }
    }


    /**
    * Update a track's tags on SoundCloud with the current Airtime tags.
    * @param soundcloud track id
    * @param $tags string
    */
    public function updateSCTags( $trackId, $tags = "" )
    {
        try {
          $token = $this->getToken();

          if ($token && Application_Model_Preference::GetUploadToSoundcloudOption()) {

            $tags .= " " . Application_Model_Preference::GetSoundCloudTags();

            //add existing track tags or they'll be overwritten
            $track = $this->getTracks(array('ids' => $trackId));
            if(isset($track[0]['tag_list'])){
                $tags .= " " . $track[0]['tag_list'];
            }

            $response = json_decode( $this->_soundcloud->put("tracks/" . $trackId, array( "track[tag_list]" => $tags ) ) );

            return $response;

          } else {    
            throw new Exception("Error. Unable to edit SouncCloud track: " .  $trackId . ", Please Check your SoundCloud preferences.");
          }

        } catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
            Logging::info($e->getMessage());
        }
    }


}

class NoSoundCloundToken extends Exception {}
