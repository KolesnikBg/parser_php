<?php
header("Content-type: text/html;charset=utf-8");
/*$context = stream_context_create(
    array(
        "http" => array(
            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
        )
    )
);

var_dump( file_get_contents("https://allegro.pl", false, $context));*/

class Curl {

    protected $url;
    protected $curl;
    protected $result;

    const CURL_REDIRECT   = 1;
    const RETURN_VARIABLE = 1;
    const TIMEOUT         = 4;
    const METHOD          = 1; // POST

    public function __construct(){
        $this->curl = curl_init();
        self::get_url();
        self::setup();
    }

    public function get_url(){
        //if (isset($_POST['ap_search_field'])){
            return $this->url = 'https://allegro.pl/oferta/mata-wygluszajaca-wygluszenia-maski-komory-silnika-5269824911';
       // }

       // return false;
    }

    public function set_url($url){
        $this->url = $url;
        self::setup();
    }

    protected function setup(){
        /*
        curl_setopt($this->curl, CURLOPT_URL, $this->url); // set url to post to
        curl_setopt($this->curl, CURLOPT_FAILONERROR, 1);
        curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, self::CURL_REDIRECT); // allow or not redirects
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, self::RETURN_VARIABLE); // return or not into a variable
        curl_setopt($this->curl, CURLOPT_TIMEOUT, self::TIMEOUT); // times out after TIMEOUT secs
        //curl_setopt($ch, CURLOPT_POST, self::METHOD); // set method
        //curl_setopt($ch, CURLOPT_POSTFIELDS, "url=index%3Dbooks&field-keywords=PHP+MYSQL"); // add POST fields
        */

        //curl_setopt($this->curl, CURLOPT_PORT, 443);
        //curl_setopt($this->curl, CURLOPT_HEADER, 0);
        curl_setopt($this->curl, CURLOPT_URL, $this->url);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($this->curl, CURLOPT_FORBID_REUSE, 1);
        //curl_setopt($this->curl, CURLOPT_FRESH_CONNECT, 1);
        //curl_setopt($this->curl, CURLOPT_POST, 1);
        curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        
		$headers = array(
			"Host: allegro.pl",
			"User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:85.0) Gecko/20100101 Firefox/85.0",
			"Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
			"Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3",
			"Accept-Encoding: identity",
			"Connection: keep-alive",
			//"Cookie: _cmuid=2pgncx9z-kaeb-55r4-dzm3-jzu7kjc6hrkz; datadome=Qn~YOxO8HLAqGth5fzV-ozJDi2q.YL~jVO.mLvatTVHLtlcW-XiN4V6lEI0xRyGH7YRgPQGMUX0jEivy_PE0oLf3SftSRSls9h-etrin4t; _ga_2FTJ836HTM=GS1.1.1614774385.2.1.1614774407.38; cartUserId=2pgncx9z-kaeb-55r4-dzm3-jzu7kjc6hrkz; _gcl_au=1.1.1890204156.1614774388; __gfp_64b=-TURNEDOFF; _ga=GA1.2.573666093.1614774391; gdpr_permission_given=1; _gid=GA1.2.543627226.1614774391",
			"Upgrade-Insecure-Requests: 1",
			"Pragma: no-cache",
			"Cache-Control: no-cache",
		);

  		$cookie = './cron.cookies.txt';
		curl_setopt($this->curl, CURLOPT_HTTPHEADER, $headers);
		//curl_setopt($this->curl, CURLOPT_COOKIEFILE, $cookie);
		//curl_setopt($this->curl, CURLOPT_COOKIEJAR, $cookie);
		        
    }

    public function process(){
        return $this->result = curl_exec($this->curl); // run the whole process
    }

    public function close(){
        curl_close($this->curl);
    }
}

$curl = new Curl();

var_dump ($curl->process());




?>