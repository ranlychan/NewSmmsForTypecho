<?php

class Smms {
    const VERSION              = '2.0';

    const API_URL_1            = 'https://sm.ms/api/v2';      // 国外
    const API_URL_2            = 'https://smms.app/api/v2';   // 国内

    const CONTENT_TYPE         = 'multipart/form-data';            // multipart/form-data

    private $_token;
    private $_username;
    private $_password;
    private $_timeout = 30;
    private $_endpoint;

	/**
	* 初始化 Smms 存储接口
	* @param $token 凭证
	* @param $username 用户名
	* @param $password 密码
    *
	* @return object
	*/
	public function __construct($token, $endpoint, $username = NULL, $password = NULL, $timeout = 30) {
		$this->_token = $token;
		$this->_username = $username;
		$this->_password = $password;
        $this->_endpoint = $endpoint;
        $this->_timeout = $timeout;
	}

    /**
     * 获取当前接口版本号
     */
    public function version() {
        return self::VERSION;
    }

    /**
     * 删除文件
     * @param string $hash 文件hash
     *
     * @return boolean
     * @throws Exception
     */
    public function delete($hash) {
        $res = $this->_do_request('GET', '/delete/' . $hash);
        if($res["success"]){
            return true;
        }
        throw new Exception('upload failed');
    }

    /**
     * 上传文件
     * @param $curl_file
     * @param string $format 返回信息格式, json或xml
     * @return mixed
     * @throws Exception
     */
    public function upload($curl_file, $format = 'json') {
        $opts['smfile'] = $curl_file;
        $opts['format'] = $format;
        $res = $this->_do_request('POST','/upload',NULL, $opts);
        if ($res['success']){
            return $res['data'];
        }
        throw new Exception('upload failed');
    }

    /**
     * 获取目录文件列表
     *
     * @param string $path 查询路径
     *
     * @return mixed
     */
//    public function getList($path = '/') {
//        $rsp = $this->_do_request('GET', $path);
//
//        $list = array();
//        if ($rsp) {
//            $rsp = explode("\n", $rsp);
//            foreach($rsp as $item) {
//                @list($name, $type, $size, $time) = explode("\t", trim($item));
//                if (!empty($time)) {
//                    $type = $type == 'N' ? 'file' : 'folder';
//                }
//
//                $item = array(
//                    'name' => $name,
//                    'type' => $type,
//                    'size' => intval($size),
//                    'time' => intval($time),
//                );
//                array_push($list, $item);
//            }
//        }
//
//        return $list;
//    }



    public function getUserProfile() {
        $res = $this->_do_request('POST', '/profile');
        if ($res["success"]) {
            return $res['data'];
        } else {
            throw new Exception($res['message']);
        }
    }

    /**
     * HTTP REQUEST 封装
     * @param string $method HTTP REQUEST方法，包括PUT、POST、GET、OPTIONS、DELETE
     * @param string $path 请求方法路径
     * @param array $headers 请求需要的特殊HTTP HEADERS
     * @param array $body 需要发送的数据
     * @param null $file_handle
     * @return mixed
     * @throws Exception
     */
    protected function _do_request($method, $path, $headers = NULL, $body= NULL, $file_handle= NULL) {
        $ch = curl_init("{$this->_endpoint}{$path}");
        $_headers = array();
        if (!empty($headers)){
            foreach($headers as $k => $v) {
                array_push($_headers, "{$k}: {$v}");
            }
        } else {
            array_push($_headers, "Authorization: {$this->_token}");
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->_timeout);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

		if($method == 'POST'){
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 0) throw new Exception('Connection Failed', $http_code);

        curl_close($ch);

        $response = json_decode($response, true);
        if($http_code == 200) {
            return $response;
        } else {
            throw new Exception($response);
        }

    }

    /**
     * 获取返回的错误信息
     *
     * @param string $header_string
     *
     * @return mixed
     */
    private function _getErrorMessage($header_string) {
        list($status, $stash) = explode("\r\n", $header_string, 2);
        list($v, $code, $message) = explode(" ", $status, 3);
        return $message;
    }


}

class SmmsImg {
    private $file_id;
    private $width;
    private $height;
    private $filename;
    private $storename;
    private $size;
    private $path;
    private $hash;
    private $url;
    private $delete;
    private $page;

    /**
     * @param $file_id
     * @param $width
     * @param $height
     * @param $filename
     * @param $storename
     * @param $size
     * @param $path
     * @param $hash
     * @param $url
     * @param $delete
     * @param $page
     */
    public function __construct($file_id, $width, $height, $filename, $storename, $size, $path, $hash, $url, $delete, $page)
    {
        $this->file_id = $file_id;
        $this->width = $width;
        $this->height = $height;
        $this->filename = $filename;
        $this->storename = $storename;
        $this->size = $size;
        $this->path = $path;
        $this->hash = $hash;
        $this->url = $url;
        $this->delete = $delete;
        $this->page = $page;
    }

    /**
     * @return mixed
     */
    public function getFileId()
    {
        return $this->file_id;
    }

    /**
     * @param mixed $file_id
     */
    public function setFileId($file_id): void
    {
        $this->file_id = $file_id;
    }

    /**
     * @return mixed
     */
    public function getWidth()
    {
        return $this->width;
    }

    /**
     * @param mixed $width
     */
    public function setWidth($width): void
    {
        $this->width = $width;
    }

    /**
     * @return mixed
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * @param mixed $height
     */
    public function setHeight($height): void
    {
        $this->height = $height;
    }

    /**
     * @return mixed
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param mixed $filename
     */
    public function setFilename($filename): void
    {
        $this->filename = $filename;
    }

    /**
     * @return mixed
     */
    public function getStorename()
    {
        return $this->storename;
    }

    /**
     * @param mixed $storename
     */
    public function setStorename($storename): void
    {
        $this->storename = $storename;
    }

    /**
     * @return mixed
     */
    public function getSize()
    {
        return $this->size;
    }

    /**
     * @param mixed $size
     */
    public function setSize($size): void
    {
        $this->size = $size;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param mixed $path
     */
    public function setPath($path): void
    {
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * @param mixed $hash
     */
    public function setHash($hash): void
    {
        $this->hash = $hash;
    }

    /**
     * @return mixed
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @param mixed $url
     */
    public function setUrl($url): void
    {
        $this->url = $url;
    }

    /**
     * @return mixed
     */
    public function getDelete()
    {
        return $this->delete;
    }

    /**
     * @param mixed $delete
     */
    public function setDelete($delete): void
    {
        $this->delete = $delete;
    }

    /**
     * @return mixed
     */
    public function getPage()
    {
        return $this->page;
    }

    /**
     * @param mixed $page
     */
    public function setPage($page): void
    {
        $this->page = $page;
    }


}