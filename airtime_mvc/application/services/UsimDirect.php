<?php
/**
* Application_Service_UsimDirect
* Interact with USIM Direct web services (http://direct.voanews.com)
* @category Services
*/


class Application_Service_UsimDirect
{
    private $_enabled;
    private $_langcode;
    private $_domain;
    private $_language_domains = array(
                "en" => "https://directbeta.voanews.com",
                "fr" => "https://directbeta.lavoixdelamerique.com",
                "ht" => "https://directbeta.voanouvel.com",
                "ha" => "https://directbeta.voahausa.com",
                "id" => "https://directbeta.voaindonesia.com",
                "ku" => "https://directbeta.dengiamerika.com",
                "pt" => "https://directbeta.voaportugues.com",
                "es" => "https://directbeta.voanoticias.com",
                "ru" => "https://directbeta.golos-ameriki.ru",
                "sw" => "https://directbeta.voaswahili.com",
                "tr" => "https://directbeta.amerikaninsesi.com",
                "uk" => "https://directbeta.chastime.com",
                "uz" => "https://directbeta.amerikaovozi.com"
         );


    public function __construct()
    {
        $CC_CONFIG = Config::getConfig();
        $this->_enabled = Application_Model_Preference::GetEnableUSIMDirect();
        $this->_langcode = Application_Model_Preference::GetUSIMDirectLanguage();
        //get the USIM Direct url based on language (default => 'en') (this shouldn't be necessary after changes to Direct API)
        $this->_domain = "https://direct.voanews.com";
        foreach ($this->_language_domains as $key => $value) {
            if($this->_langcode == $key){
                $this->_domain = $value;
            }
        }
    }



    public function getTempDirectory()
    {
        $tmp = ini_get("upload_tmp_dir") . DIRECTORY_SEPARATOR . "usimdirect" . DIRECTORY_SEPARATOR;
        if (!file_exists($tmp)){
            @mkdir($tmp, 0775, true);
        }
        return $tmp;
    }

    /**
    * Get tracks from USIM Direct Webservices.
    * @param $options Soundcloud API "get" options
    * @return request
    *
    */
    public function getTracks($options = null)
    {
        try{
            //TODO switch url based on user Language preferences
            $url = $this->_domain . "/api/REST/kaltura.json";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $response = curl_exec($ch);
            
            return json_decode($response);

        } catch(Exception $e){

            return array("error"=>$e->getMessage());
        }
    }


    /**
    * Import a track from USIM Direct.
    * @param int track id
    */
    public function importTrack($trackId)
    {
        //example https://directbeta.voanews.com/api/REST/node/44478.json
        try {
            
            $url = $this->_domain . "/api/REST/node/" . trim($trackId) .".json";
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            $track = json_decode(curl_exec($ch));
            curl_close ($ch);

            $success = false;

            if($track){
                //get the correct download file
                switch ($type = strtolower($track->field_file_type->und[0]->value)) {
                    case 'audio':
                        $remote_file = $track->field_file_url->und[0]->url;
                        break;
                    case 'video':
                        $remote_file = $track->field_audio_transcript_file_url->und[0]->url;
                        break;
                    default:
                       $remote_file = $track->field_source_file_url->und[0]->url;
                }

                //create a unique temp filename based on track id
                $tmpTitle = 'USIMDirect_Track_' . $trackId;
                //TODO: get the file extension from $remote_file string
                $tmpFilename = $tmpTitle . '.mp3';
                //get the temp dir
                $destination = $this->getTempDirectory();
                //download the file
                $curlOptions = array('CURLOPT_FOLLOWLOCATION' => 1, 'CURLOPT_HEADER' => 1);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $remote_file);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                $data = curl_exec($ch);
                Logging::info("Import USIM Direct file: $tmpFilename");
                $file = fopen($destination . $tmpFilename, "w+");
                fputs($file, $data);
                fclose($file);
                $curl_info = curl_getinfo($ch);
                curl_close ($ch);

            }
            
            $info = array();
            if($data == true && file_exists($destination . $tmpFilename)){
                //sucessfully downloaded the media file and moved to tmp dir

                //create the .metadata file
                $mdFile = $this->createMetadataFile($tmpTitle, $track);
                $info['filename'] = $tmpTitle;
                $info['metadata'] = $mdFile;
                $message = "";
                //Copy media file to watch folder
                $copy = Application_Model_StoredFile::copyFileToStor($destination, $tmpFilename, $tmpFilename);

                if(is_null($copy)){
                    $success = true;
                    $response = array('success' => $success, 'message' => "Copied file to watch folder.", 'info' => $info);
                } else {
                    //fail to move to watch folder
                    $info['error'] = $copy;
                    $response = array('success' => false, 'message' => "Failed to copy.", 'info' => $info);
                }

            } else {
              //cURL failed or unable to write to tmp directory
              $response = array('success' => false, 'error' => curl_error($ch), 'info' => $info);

            }
            
          return $response;

        } catch (Services_Soundcloud_Invalid_Http_Response_Code_Exception $e) {
            Logging::info($e->getMessage());
        }
    }

    /**
    * Create a .metadata file of a track imported from USIM Direct.
    * .metadata file contains a newline for each metadata item. e.g. "key=value"
    * @param $filename string
    * @param obj $track track data
    */
    public function createMetadataFile($filename, $track)
    {
        try {

            if($track->nid){
                $mdFilename = "$filename.metadata";
                $destination = $this->getTempDirectory();
                $filepath = $destination . $mdFilename;
                //create metadata to output in file
                $md = array();
                if(!empty($track->title)){
                    $md['track_title'] = $track->title;
                }
                if(!empty($track->path)){
                    $md['info_url'] = $track->path;
                }
                if(!empty($track->field_credit->und[0]->value)){
                    $md['artist_name'] = $track->field_credit->und[0]->value;
                }
                if(!empty($track->language)){
                    $md['language'] = $track->language;
                }
                $md['album_title'] = 'USIM Direct';

                $content = "";
                foreach($md as $key => $value){
                    if(!empty($md[$key])){
                        //strip newlines
                        $cleanVal = trim(preg_replace('/\s\s+/', ' ', $value));
                        $content .= "$key=$cleanVal\n";
                    }
                }
                //mark that this file is from USIM Direct
                $content .= "created_with=usimdirect\n";

                //create metadata file in tmp dir
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


          return FALSE;

        } catch (Exception $e) {
            Logging::info($e->getMessage());
        }

    }




}

