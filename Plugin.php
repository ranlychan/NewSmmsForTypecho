<?php
/**
 * NewSmms 新SMMS图床
 * 
 * @package NewSmms
 * @author ranlychan
 * @version 1.0.0
 * @link https://www.ranlychan.com
 * @dependence 1.0-*
 * @date 2023-3-1
 */

include_once 'smms.class.php';

class NewSmmsForTypecho_Plugin implements Typecho_Plugin_Interface
{
	//上传文件目录
	const UPLOAD_DIR = '/usr/uploads' ;
	
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     * 
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate()
    {
        Typecho_Plugin::factory('Widget_Upload')->uploadHandle = array('NewSmmsForTypecho_Plugin', 'uploadHandle');
        Typecho_Plugin::factory('Widget_Upload')->modifyHandle = array('NewSmmsForTypecho_Plugin', 'modifyHandle');
        Typecho_Plugin::factory('Widget_Upload')->deleteHandle = array('NewSmmsForTypecho_Plugin', 'deleteHandle');
        Typecho_Plugin::factory('Widget_Upload')->attachmentHandle = array('NewSmmsForTypecho_Plugin', 'attachmentHandle');
        Typecho_Plugin::factory('Widget_Upload')->attachmentDataHandle = array('NewSmmsForTypecho_Plugin', 'attachmentDataHandle');
		return _t('插件激活成功,记得设置~');
    }
    
    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     * 
     * @static
     * @access public
     * @return string
     */
    public static function deactivate(){
		return _t('插件禁用成功');
	}
    
    /**
     * 获取插件配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $smmsHost= new Typecho_Widget_Helper_Form_Element_Radio('smmsHost',
            array( Smms::API_URL_1 => 'sm.ms', Smms::API_URL_2 => 'smms.app'),
            Smms::API_URL_2,                          // 默认选中项
            '服务域名',                                 // radio组标题
            _t('sm.ms服务国外，smms.app服务国内'));      // 说明文字
        $form->addInput($smmsHost);

        $smmsToken = new Typecho_Widget_Helper_Form_Element_Password('smmsToken', NULL, NULL,
            _t('API Token：'),
            _t('去https://smms.app/注册登录账号后，点击右上角User->Dashboard，新页面点击左侧API Token，得到Secret Token 禁用插件后需要重写填写，但原来上传的图片不受影响')
        );
		$form->addInput($smmsToken->addRule('required',_t('您必须填写API Token'))
		->addRule(array('NewSmmsForTypecho_Plugin', 'validate'), _t('验证不通过，请核对API Token是否正确')));
    }
    
    /**
     * 个人用户的配置面板
     * 
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}
    
    /**
     * 上传文件处理函数
     *
     * @access public
     * @param array $file 上传的文件
     * @return mixed
     */
    public static function uploadHandle($file)
    {
        // 文件名检查
        if (empty($file['name'])) {
            return false;
        }

        $result = Typecho_Plugin::factory('Widget_Upload')->trigger($hasUploaded)->uploadHandle($file);
        if ($hasUploaded) {
            return $result;
        }

        // 获取扩展名并检查
        $ext = self::getSafeName($file['name']);
        if (!Widget_Upload::checkFileType($ext) || Typecho_Common::isAppEngine()) {
            return false;
        }

        //获取文件名及文件路径
        $fileName = sprintf('%u', crc32(uniqid())) . '.' . $ext;
        $uploadFileTemp = self::getUploadFile($file);
        // 如果没有临时文件，则退出
        if (!isset($uploadFileTemp)) {
            return false;
        }

        if(self::isImgType($ext)){
            // 上传到图床
            try {
                $curl_file = new \CURLFile(realpath($uploadFileTemp),"",$fileName);
                $smms = self::smmsInit();
                $data = $smms->upload($curl_file);
                $path = $data['path'];                                     // 相对路径
                //返回相对存储路径,存于数据库[prefix]_contents表的text属性内
                return array(
                    'name' => $file['name'],                               // 附件名称
                    'path' => $path,                                       // 附件存储相对路径
                    'size' => $file['size'],                               // 附件比特大小
                    'type' => $ext,                                        // 附件扩展后缀名
                    'mime' => self::mimeContentType($path),                // mime类型
                    /* 下面是自定义的扩展元数据 */
                    'hash_algo' => 'hash',                                  // 计算hash值所用的算法
                    'hash' => $data['hash'],//hash_file('md5',$uploadFileTemp),       // 附件hash值
                    'url' => $data['url'],                                 // 附件绝对路径
                    'source' => 'smms'                                     // 附件存储位置默认本机 loc:本机, smms:这个图床
                );
            } catch (Exception $e) {
                return false;
            }
        } else {
            // 上传到本地
            return Widget_Upload::uploadHandle($file);
        }
    }

    /**
     * 修改文件处理函数
     *
     * @access public
     * @param array $content 老文件
     * @param array $file 新上传的文件
     * @return mixed
     */
    public static function modifyHandle($content, $file)
    {
        switch ($content['attachment']->source){
            case "smms":  // smms图床存储
                return self::uploadHandle($file);
            case "loc":   // 本机存储
            default:      // 默认也为本机存储的处理方式以兼容旧的数据
                return Widget_Upload::modifyHandle($content, $file);
        }

    }

    /**
     * 删除文件
     *
     * @access public
     * @param array $content 文件相关信息
     * @return string
     */
    public static function deleteHandle(array $content)
    {
        switch ($content['attachment']->source){
            case "smms":  // smms图床存储
                $result = Typecho_Plugin::factory('Widget_Upload')->trigger($hasDeleted)->deleteHandle($content);
                if ($hasDeleted) {
                    return $result;
                }

                try {
                    $smms = self::smmsInit();
                    $hash = $content['attachment']->hash;
                    return $smms->delete($hash);
                } catch (Exception $e) {
                    return false;
                }
            case "loc":   // 本机存储
            default:      // 默认也为本机存储的处理方式以兼容旧的数据
                return Widget_Upload::deleteHandle($content);
        }

    }

    /**
     * 获取实际文件绝对访问路径
     *
     * @access public
     * @param array $content 单个文件的相关信息列表
     * @return string
     */
    public static function attachmentHandle(array $content)
    {
        // 根据attachment的存储位置(本地还是图床等)来获取绝对路径
        switch ($content['attachment']->source){
            case "smms":  // smms图床存储
                $result = Typecho_Plugin::factory('Widget_Upload')->trigger($hasPlugged)->attachmentHandle($content);
                if ($hasPlugged) {
                    return $result;
                }
                return $content['attachment']->url;
            case "loc":   // 本机存储
            default:      // 默认也为本机存储的处理方式以兼容旧的数据
                return Widget_Upload::attachmentHandle($content);
        }
    }

    /**
     * 获取实际文件数据
     *
     * @access public
     * @param array $content
     * @return string
     */
    public static function attachmentDataHandle(array $content)
    {
        switch ($content['attachment']->source){
            case "smms":  // smms图床存储
                $result = Typecho_Plugin::factory('Widget_Upload')->trigger($hasPlugged)->attachmentDataHandle($content);
                if ($hasPlugged) {
                    return $result;
                }

                return file_get_contents($content['attachment']->url);
            case "loc":   // 本机存储
            default:      // 默认也为本机存储的处理方式以兼容旧的数据
                return Widget_Upload::attachmentDataHandle($content);
        }
    }

	/**
     * 验证Smms Api Token
     * 
     * @access public
     * 
     * @return boolean
     */
	public static function validate()
	{
		$host = Typecho_Request::getInstance()->smmsHost;
		$token = Typecho_Request::getInstance()->smmsToken;
		try{
			$smms = new Smms($token,$host);
			$_usrProfile = $smms->getUserProfile();
            return !empty($_usrProfile);
		}
		catch(Exception $e){
			return false;
		}
	}

    /**
     * 初始化Smms
     *
     * @access public
     * @return object
     */
    public static function smmsInit()
    {
        $options = Typecho_Widget::widget('Widget_Options')->plugin('NewSmmsForTypecho');
        return new Smms($options->smmsToken, $options->smmsHost);
    }

	/**
     * 获取上传文件
     *
	 * @param array $file 上传的文件
     * @access private
     * @return string
     */
	private static function getUploadFile($file)
	{
		return isset($file['tmp_name']) ? $file['tmp_name'] : (isset($file['bytes']) ? $file['bytes'] : (isset($file['bits']) ? $file['bits'] : ''));	
	}
	
	/**
     * 获取安全的文件名 
     * 
     * @param string $name 
     * @static
     * @access private
     * @return string
     */
	private static function getSafeName(&$name)
	{
		$name = str_replace(array('"', '<', '>'), '', $name);
        $name = str_replace('\\', '/', $name);
        $name = false === strpos($name, '/') ? ('a' . $name) : str_replace('/', '/a', $name);
        $info = pathinfo($name);
        $name = substr($info['basename'], 1);
    
        return isset($info['extension']) ? strtolower($info['extension']) : '';
	}
	
	/**
	*获取文件上传目录
	* @access private
    * @return string
	*/
	private static function getUploadDir()
	{
		if(defined('__TYPECHO_UPLOAD_DIR__'))
		{
			return __TYPECHO_UPLOAD_DIR__;
		}
		else{
			return self::UPLOAD_DIR;
		}
	}
	
	/**
	*获取文件Mime类型，处理掉异常
	* @access private
    * @return string
	*/
	private static function mimeContentType($fileName){
		//TODO:避免该方法
		//避免Typecho mime-content-type引发的异常 
		/*异常详情：
		*Warning</b>:  mime_content_type(/path/filename.jpg) [<a href='function.mime-content-type'>function.mime-content-type</a>]: 
		*failed to open stream: No such file or directory in <b>/webroot/var/Typecho/Common.php</b> on line <b>1058</b>
		*/
		@$mime = Typecho_Common::mimeContentType($fileName);
		
		if(!$mime){
			return self::getMime($fileName);
		}
		else{
			return $mime;
		}
	}
	
	/**
	*获取文件Mime类型
	* @access private
    * @return string
	*/
	private static function getMime($fileName){
		$mimeTypes = array(
          'ez' => 'application/andrew-inset',
          'csm' => 'application/cu-seeme',
          'cu' => 'application/cu-seeme',
          'tsp' => 'application/dsptype',
          'spl' => 'application/x-futuresplash',
          'hta' => 'application/hta',
          'cpt' => 'image/x-corelphotopaint',
          'hqx' => 'application/mac-binhex40',
          'nb' => 'application/mathematica',
          'mdb' => 'application/msaccess',
          'doc' => 'application/msword',
          'dot' => 'application/msword',
          'bin' => 'application/octet-stream',
          'oda' => 'application/oda',
          'ogg' => 'application/ogg',
          'prf' => 'application/pics-rules',
          'key' => 'application/pgp-keys',
          'pdf' => 'application/pdf',
          'pgp' => 'application/pgp-signature',
          'ps' => 'application/postscript',
          'ai' => 'application/postscript',
          'eps' => 'application/postscript',
          'rss' => 'application/rss+xml',
          'rtf' => 'text/rtf',
          'smi' => 'application/smil',
          'smil' => 'application/smil',
          'wp5' => 'application/wordperfect5.1',
          'xht' => 'application/xhtml+xml',
          'xhtml' => 'application/xhtml+xml',
          'zip' => 'application/zip',
          'cdy' => 'application/vnd.cinderella',
          'mif' => 'application/x-mif',
          'xls' => 'application/vnd.ms-excel',
          'xlb' => 'application/vnd.ms-excel',
          'cat' => 'application/vnd.ms-pki.seccat',
          'stl' => 'application/vnd.ms-pki.stl',
          'ppt' => 'application/vnd.ms-powerpoint',
          'pps' => 'application/vnd.ms-powerpoint',
          'pot' => 'application/vnd.ms-powerpoint',
          'sdc' => 'application/vnd.stardivision.calc',
          'sda' => 'application/vnd.stardivision.draw',
          'sdd' => 'application/vnd.stardivision.impress',
          'sdp' => 'application/vnd.stardivision.impress',
          'smf' => 'application/vnd.stardivision.math',
          'sdw' => 'application/vnd.stardivision.writer',
          'vor' => 'application/vnd.stardivision.writer',
          'sgl' => 'application/vnd.stardivision.writer-global',
          'sxc' => 'application/vnd.sun.xml.calc',
          'stc' => 'application/vnd.sun.xml.calc.template',
          'sxd' => 'application/vnd.sun.xml.draw',
          'std' => 'application/vnd.sun.xml.draw.template',
          'sxi' => 'application/vnd.sun.xml.impress',
          'sti' => 'application/vnd.sun.xml.impress.template',
          'sxm' => 'application/vnd.sun.xml.math',
          'sxw' => 'application/vnd.sun.xml.writer',
          'sxg' => 'application/vnd.sun.xml.writer.global',
          'stw' => 'application/vnd.sun.xml.writer.template',
          'sis' => 'application/vnd.symbian.install',
          'wbxml' => 'application/vnd.wap.wbxml',
          'wmlc' => 'application/vnd.wap.wmlc',
          'wmlsc' => 'application/vnd.wap.wmlscriptc',
          'wk' => 'application/x-123',
          'dmg' => 'application/x-apple-diskimage',
          'bcpio' => 'application/x-bcpio',
          'torrent' => 'application/x-bittorrent',
          'cdf' => 'application/x-cdf',
          'vcd' => 'application/x-cdlink',
          'pgn' => 'application/x-chess-pgn',
          'cpio' => 'application/x-cpio',
          'csh' => 'text/x-csh',
          'deb' => 'application/x-debian-package',
          'dcr' => 'application/x-director',
          'dir' => 'application/x-director',
          'dxr' => 'application/x-director',
          'wad' => 'application/x-doom',
          'dms' => 'application/x-dms',
          'dvi' => 'application/x-dvi',
          'pfa' => 'application/x-font',
          'pfb' => 'application/x-font',
          'gsf' => 'application/x-font',
          'pcf' => 'application/x-font',
          'pcf.Z' => 'application/x-font',
          'gnumeric' => 'application/x-gnumeric',
          'sgf' => 'application/x-go-sgf',
          'gcf' => 'application/x-graphing-calculator',
          'gtar' => 'application/x-gtar',
          'tgz' => 'application/x-gtar',
          'taz' => 'application/x-gtar',
          'gz'  => 'application/x-gtar',
          'hdf' => 'application/x-hdf',
          'phtml' => 'application/x-httpd-php',
          'pht' => 'application/x-httpd-php',
          'php' => 'application/x-httpd-php',
          'phps' => 'application/x-httpd-php-source',
          'php3' => 'application/x-httpd-php3',
          'php3p' => 'application/x-httpd-php3-preprocessed',
          'php4' => 'application/x-httpd-php4',
          'ica' => 'application/x-ica',
          'ins' => 'application/x-internet-signup',
          'isp' => 'application/x-internet-signup',
          'iii' => 'application/x-iphone',
          'jar' => 'application/x-java-archive',
          'jnlp' => 'application/x-java-jnlp-file',
          'ser' => 'application/x-java-serialized-object',
          'class' => 'application/x-java-vm',
          'js' => 'application/x-javascript',
          'chrt' => 'application/x-kchart',
          'kil' => 'application/x-killustrator',
          'kpr' => 'application/x-kpresenter',
          'kpt' => 'application/x-kpresenter',
          'skp' => 'application/x-koan',
          'skd' => 'application/x-koan',
          'skt' => 'application/x-koan',
          'skm' => 'application/x-koan',
          'ksp' => 'application/x-kspread',
          'kwd' => 'application/x-kword',
          'kwt' => 'application/x-kword',
          'latex' => 'application/x-latex',
          'lha' => 'application/x-lha',
          'lzh' => 'application/x-lzh',
          'lzx' => 'application/x-lzx',
          'frm' => 'application/x-maker',
          'maker' => 'application/x-maker',
          'frame' => 'application/x-maker',
          'fm' => 'application/x-maker',
          'fb' => 'application/x-maker',
          'book' => 'application/x-maker',
          'fbdoc' => 'application/x-maker',
          'wmz' => 'application/x-ms-wmz',
          'wmd' => 'application/x-ms-wmd',
          'com' => 'application/x-msdos-program',
          'exe' => 'application/x-msdos-program',
          'bat' => 'application/x-msdos-program',
          'dll' => 'application/x-msdos-program',
          'msi' => 'application/x-msi',
          'nc' => 'application/x-netcdf',
          'pac' => 'application/x-ns-proxy-autoconfig',
          'nwc' => 'application/x-nwc',
          'o' => 'application/x-object',
          'oza' => 'application/x-oz-application',
          'pl' => 'application/x-perl',
          'pm' => 'application/x-perl',
          'p7r' => 'application/x-pkcs7-certreqresp',
          'crl' => 'application/x-pkcs7-crl',
          'qtl' => 'application/x-quicktimeplayer',
          'rpm' => 'audio/x-pn-realaudio-plugin',
          'shar' => 'application/x-shar',
          'swf' => 'application/x-shockwave-flash',
          'swfl' => 'application/x-shockwave-flash',
          'sh' => 'text/x-sh',
          'sit' => 'application/x-stuffit',
          'sv4cpio' => 'application/x-sv4cpio',
          'sv4crc' => 'application/x-sv4crc',
          'tar' => 'application/x-tar',
          'tcl' => 'text/x-tcl',
          'tex' => 'text/x-tex',
          'gf' => 'application/x-tex-gf',
          'pk' => 'application/x-tex-pk',
          'texinfo' => 'application/x-texinfo',
          'texi' => 'application/x-texinfo',
          '~' => 'application/x-trash',
          '%' => 'application/x-trash',
          'bak' => 'application/x-trash',
          'old' => 'application/x-trash',
          'sik' => 'application/x-trash',
          't' => 'application/x-troff',
          'tr' => 'application/x-troff',
          'roff' => 'application/x-troff',
          'man' => 'application/x-troff-man',
          'me' => 'application/x-troff-me',
          'ms' => 'application/x-troff-ms',
          'ustar' => 'application/x-ustar',
          'src' => 'application/x-wais-source',
          'wz' => 'application/x-wingz',
          'crt' => 'application/x-x509-ca-cert',
          'fig' => 'application/x-xfig',
          'au' => 'audio/basic',
          'snd' => 'audio/basic',
          'mid' => 'audio/midi',
          'midi' => 'audio/midi',
          'kar' => 'audio/midi',
          'mpga' => 'audio/mpeg',
          'mpega' => 'audio/mpeg',
          'mp2' => 'audio/mpeg',
          'mp3' => 'audio/mpeg',
          'm3u' => 'audio/x-mpegurl',
          'sid' => 'audio/prs.sid',
          'aif' => 'audio/x-aiff',
          'aiff' => 'audio/x-aiff',
          'aifc' => 'audio/x-aiff',
          'gsm' => 'audio/x-gsm',
          'wma' => 'audio/x-ms-wma',
          'wax' => 'audio/x-ms-wax',
          'ra' => 'audio/x-realaudio',
          'rm' => 'audio/x-pn-realaudio',
          'ram' => 'audio/x-pn-realaudio',
          'pls' => 'audio/x-scpls',
          'sd2' => 'audio/x-sd2',
          'wav' => 'audio/x-wav',
          'pdb' => 'chemical/x-pdb',
          'xyz' => 'chemical/x-xyz',
          'bmp' => 'image/x-ms-bmp',
          'gif' => 'image/gif',
          'ief' => 'image/ief',
          'jpeg' => 'image/jpeg',
          'jpg' => 'image/jpeg',
          'jpe' => 'image/jpeg',
          'pcx' => 'image/pcx',
          'png' => 'image/png',
          'svg' => 'image/svg+xml',
          'svgz' => 'image/svg+xml',
          'tiff' => 'image/tiff',
          'tif' => 'image/tiff',
          'wbmp' => 'image/vnd.wap.wbmp',
          'ras' => 'image/x-cmu-raster',
          'cdr' => 'image/x-coreldraw',
          'pat' => 'image/x-coreldrawpattern',
          'cdt' => 'image/x-coreldrawtemplate',
          'djvu' => 'image/x-djvu',
          'djv' => 'image/x-djvu',
          'ico' => 'image/x-icon',
          'art' => 'image/x-jg',
          'jng' => 'image/x-jng',
          'psd' => 'image/x-photoshop',
          'pnm' => 'image/x-portable-anymap',
          'pbm' => 'image/x-portable-bitmap',
          'pgm' => 'image/x-portable-graymap',
          'ppm' => 'image/x-portable-pixmap',
          'rgb' => 'image/x-rgb',
          'xbm' => 'image/x-xbitmap',
          'xpm' => 'image/x-xpixmap',
          'xwd' => 'image/x-xwindowdump',
          'igs' => 'model/iges',
          'iges' => 'model/iges',
          'msh' => 'model/mesh',
          'mesh' => 'model/mesh',
          'silo' => 'model/mesh',
          'wrl' => 'x-world/x-vrml',
          'vrml' => 'x-world/x-vrml',
          'csv' => 'text/comma-separated-values',
          'css' => 'text/css',
          '323' => 'text/h323',
          'htm' => 'text/html',
          'html' => 'text/html',
          'uls' => 'text/iuls',
          'mml' => 'text/mathml',
          'asc' => 'text/plain',
          'txt' => 'text/plain',
          'text' => 'text/plain',
          'diff' => 'text/plain',
          'rtx' => 'text/richtext',
          'sct' => 'text/scriptlet',
          'wsc' => 'text/scriptlet',
          'tm' => 'text/texmacs',
          'ts' => 'text/texmacs',
          'tsv' => 'text/tab-separated-values',
          'jad' => 'text/vnd.sun.j2me.app-descriptor',
          'wml' => 'text/vnd.wap.wml',
          'wmls' => 'text/vnd.wap.wmlscript',
          'xml' => 'text/xml',
          'xsl' => 'text/xml',
          'h++' => 'text/x-c++hdr',
          'hpp' => 'text/x-c++hdr',
          'hxx' => 'text/x-c++hdr',
          'hh' => 'text/x-c++hdr',
          'c++' => 'text/x-c++src',
          'cpp' => 'text/x-c++src',
          'cxx' => 'text/x-c++src',
          'cc' => 'text/x-c++src',
          'h' => 'text/x-chdr',
          'c' => 'text/x-csrc',
          'java' => 'text/x-java',
          'moc' => 'text/x-moc',
          'p' => 'text/x-pascal',
          'pas' => 'text/x-pascal',
          '***' => 'text/x-pcs-***',
          'shtml' => 'text/x-server-parsed-html',
          'etx' => 'text/x-setext',
          'tk' => 'text/x-tcl',
          'ltx' => 'text/x-tex',
          'sty' => 'text/x-tex',
          'cls' => 'text/x-tex',
          'vcs' => 'text/x-vcalendar',
          'vcf' => 'text/x-vcard',
          'dl' => 'video/dl',
          'fli' => 'video/fli',
          'gl' => 'video/gl',
          'mpeg' => 'video/mpeg',
          'mpg' => 'video/mpeg',
          'mpe' => 'video/mpeg',
          'qt' => 'video/quicktime',
          'mov' => 'video/quicktime',
          'mxu' => 'video/vnd.mpegurl',
          'dif' => 'video/x-dv',
          'dv' => 'video/x-dv',
          'lsf' => 'video/x-la-asf',
          'lsx' => 'video/x-la-asf',
          'mng' => 'video/x-mng',
          'asf' => 'video/x-ms-asf',
          'asx' => 'video/x-ms-asf',
          'wm' => 'video/x-ms-wm',
          'wmv' => 'video/x-ms-wmv',
          'wmx' => 'video/x-ms-wmx',
          'wvx' => 'video/x-ms-wvx',
          'avi' => 'video/x-msvideo',
          'movie' => 'video/x-sgi-movie',
          'ice' => 'x-conference/x-cooltalk',
          'vrm' => 'x-world/x-vrml',
          'rar' => 'application/x-rar-compressed',
          'cab' => 'application/vnd.ms-cab-compressed'
        );

        $part = explode('.', $fileName);
        $size = count($part);

        if ($size > 1) {
            $ext = $part[$size - 1];
            if (isset($mimeTypes[$ext])) {
                return $mimeTypes[$ext];
            }
        }

        return 'application/octet-stream';
	}

    /**
     * 检查文件拓展是否为常见图片
     *
     * @access private
     * @param string $ext 扩展名
     * @return boolean
     */
    public static function isImgType($ext){
        return in_array(strtolower($ext), array('jpg','jpge','png','gif','svg','webp','tiff','bmp'));
    }
}
