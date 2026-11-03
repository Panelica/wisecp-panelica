<?php

    class Panelica_Module extends ServerModule
    {
        private $api,$storage=[];

        function __construct($server,$options=[]){
            $this->force_setup  = false;
            $this->_name = __CLASS__;
            parent::__construct($server,$options);
        }

        public function define_server_info($server=[])
        {
            if(!class_exists("PanelicaWisecpApi")) include __DIR__.DS."api.php";
            $this->api = new PanelicaWisecpApi($server);
        }

        public function testConnect(){
            $test       = $this->api->verify_connection();

            if(!$test){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }
        public function getPlans(){
            return false;
        }
        public function createAccount($domain,$options=[]){
            $domain         = idn_to_ascii($domain,0,INTL_IDNA_VARIANT_UTS46);
            $username       = $this->UsernameGenerator($domain);
            if(isset($options["username"])) $username = $options["username"];
            if(isset($options["password"])) $password = $options["password"];

            $password       = Utility::generate_hash(12,false,"lud");
            if(!$domain) $username = Utility::generate_hash(16,false,"lud");


            $username       = str_replace("-","",$username);

            $creation_info  = isset($options["creation_info"]) ? $options["creation_info"] : [];

            $package        = isset($creation_info["plan"]) ? $creation_info["plan"] : 'Default';
            if(!$package) $package = "Default";

            $websitesLimit  = isset($creation_info["websitesLimit"]) ? $creation_info["websitesLimit"] : 1;
            $acl            = isset($creation_info["acl"]) ? $creation_info["acl"] : 'user';


            $create     = $this->api->create_new_account($domain,$this->user["email"],$package,$username,$password,$websitesLimit,$acl);
            if(!$create){
                $this->error = $this->api->error;
                return false;
            }

            return [
                'username' => $username,
                'password' => $password,
            ];
        }

        public function modifyAccount($params=[]){
            return false;
        }

        public function apply_options($old_options,$new_options=[]){
            $old_config     = isset($old_options["config"]) ? $old_options["config"] : [];
            $new_config     = isset($new_options["config"]) ? $new_options["config"] : [];

            $old_c_info     = isset($old_options["creation_info"]) ? $old_options["creation_info"] : [];
            $new_c_info     = isset($new_options["creation_info"]) ? $new_options["creation_info"] : [];

            $old_c_user     = isset($old_config["user"]) ? $old_config["user"] : '';
            $new_c_user     = isset($new_config["user"]) ? $new_config["user"] : '';


            $old_plan       = isset($old_c_info["plan"]) ? $old_c_info["plan"] : false;
            $new_plan       = isset($new_c_info["plan"]) ? $new_c_info["plan"] : false;

            if($old_c_user !== $new_c_user && $new_c_user){
                if(!$this->api->getUserInfo($new_c_user)){
                    $this->error = $this->api->error;
                    return false;
                }
            }

            $old_password           = isset($old_config["password"]) ? $old_config["password"] : '';
            $password               = Filter::password($new_config["password"]);
            $c_password             = $password ? Crypt::encode($password,Config::get("crypt/user")) : '';
            $new_config["password"] = $c_password;

            if($new_c_user && $password && $c_password != $old_password){

                if(Utility::strlen($password) < 5){
                    $this->error = __("admin/orders/error8");
                    return false;
                }
                $strength = $this->getPasswordStrength($password);
                if(!$strength) return false;

                if($strength < 65){
                    $this->error = __("admin/orders/error9");
                    return false;
                }

                $changed    = $this->changePassword(false,$password);
                if(!$changed) return false;
            }

            $new_options["ftp_info"]["host"] = "ftp.".$new_options["domain"];
            $new_options["ftp_info"]["port"] = 21;
            $new_options["ftp_info"]["username"] = $new_config["user"];
            $new_options["ftp_info"]["password"] = $c_password;


            if($new_c_user){
                if($new_plan && !Validation::isEmpty($new_plan)){
                    if(!$old_plan || $new_plan != $old_plan){
                        $change  = $this->change_plan($new_plan);
                        if(!$change) return false;
                    }
                }
            }


            $new_options["config"]          = $new_config;
            $new_options["creation_info"]   = $new_c_info;

            return $new_options;
        }

        public function apply_updowngrade($orderopt=[],$product=[]){
            $creation_info      = isset($product["module_data"]["create_account"]) ? $product["module_data"]["create_account"] : [];
            if(!$creation_info || isset($product["module_data"]["plan"])) $creation_info = $product["module_data"];

            $p_plan             = isset($creation_info["plan"]) ? $creation_info["plan"] : false;

            if($p_plan){
                $change  = $this->change_plan($p_plan);
                if(!$change) return false;
            }


            return true;
        }

        public function UsernameGenerator($domain='',$half_mixed=false){
            $exp            = explode(".",$domain);
            $domain         = Filter::transliterate($exp[0]);
            $username       = $domain;
            $fchar          = substr($username,0,1);
            $size           = strlen($username);
            if($fchar == "0" || (int)$fchar)
                $username   = Utility::generate_hash(1,false,"l").substr($username,1,$size-1);

            /*if($half_mixed){
                $username   = substr($username,0,10);
                if($size>=8){
                    $username   = substr($username,0,4);
                    $username .= Utility::generate_hash(4,false,"d");
                }elseif($size>4 && $size<8){
                    $username   = substr($username,0,5);
                    $username .= Utility::generate_hash(3,false,"d");
                }elseif($size>=1 && $size<5){
                    $how        = (8 - $size);
                    $username   = substr($username,0,$size);
                    $username .= Utility::generate_hash($how,false,"d");
                }
            }else*/if($size>=8){
                $username   = substr($username,0,5);
                $username .= Utility::generate_hash(3,false,"l");
            }elseif($size>4 && $size<9){
                $username   = substr($username,0,5);
                $username .= Utility::generate_hash(3,false,"l");
            }elseif($size>=1 && $size<5){
                $how        = (8 - $size);
                $username   = substr($username,0,$size);
                $username .= Utility::generate_hash($how,false,"l");
            }

            return $username;
        }
        public function getBandwidth($user=false){
            return false;
        }
        public function getDisk($user=false){
            return false;
        }
        public function getSummary(){
            return false;
        }

        public function getEmailList(){
            return false;
        }
        public function getForwardsList(){
            return false;
        }
        public function addNewEmail($username,$domain,$password,$quota,$unlimited){
            return true;
        }
        public function addNewEmailForward($domain,$dest,$forward){
            return true;
        }
        public function setPassword($domain,$email,$password){
            return true;
        }
        public function setQuota($domain,$email,$quota,$unlimited){
            return true;
        }
        public function deleteEmail($domain,$email){
            return true;
        }
        public function deleteEmailForward($dest,$forward){
            return true;
        }
        public function getPasswordStrength($password){
            return 100;
        }
        public function getMailDomains(){
            return false;
        }
        public function getDomains(){
            return false;
        }

        public function changePassword($oldpw,$newpw){
            $user   = $this->config["user"];
            $apply  = $this->api->change_account_password($user,$newpw);
            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }

        public function setReseller($user,$params=[]){
            return true;
        }

        public function removeAccount($user=false){
            if(!$user) $user = $this->config["user"];
            $apply      = $this->api->terminate_account($this->options["domain"]);
            if(!$apply){
                $this->error = $this->api->error;
                if(stristr($this->error,'he server could not be deleted')) return true;
                return false;
            }
            return true;
        }

        public function removeReseller($user=false){
            return $this->removeAccount($user);
        }

        public function setupReseller($user=false,$params=[]){
            return true;
        }

        public function suspend(){
            $apply      = $this->api->change_account_status($this->options["domain"],"Suspend");
            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }
            return true;
        }

        public function suspend_reseller(){
            return $this->suspend();
        }

        public function unsuspend(){
            $apply      = $this->api->change_account_status($this->options["domain"],"Activate");
            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function unsuspend_reseller(){
            return $this->unsuspend();
        }

        public function change_plan($plan){
            if($plan == '') return true;

            $apply      = $this->api->change_account_package($this->options["domain"],$plan);
            if(!$apply){
                $this->error = $this->api->error;
                return false;
            }

            return true;
        }

        public function change_quota($quota){
            return true;
        }

        public function change_bandwidth($bwlimit){
            return true;
        }

        public function use_method($param=''){
            $method_name        = '';
            $method_prefix_c    = 'use_clientArea_';
            $method_prefix_a    = 'use_adminArea_';
            $param              = str_replace("-","_",$param);

            if(defined("ADMINISTRATOR") && $param)
                $method_name = method_exists($this,$method_prefix_a.$param) ? $method_prefix_a.$param : '';
            elseif($param)
                $method_name = method_exists($this,$method_prefix_c.$param) ? $method_prefix_c.$param : '';

            if($method_name) return $this->{$method_name}();
        }

        public function panel_links_for_client(){
            $buttons                = [];

            if(method_exists($this,'use_clientArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'       => $this->area_link . "?inc=use_method&method=SingleSignOn",
                    'name'      => __("website/account_products/login-panel"),
                ];

            if(method_exists($this,'use_clientArea_webMail'))
                $buttons["mail"] = [
                    'url' => $this->area_link . "?inc=use_method&method=webMail",
                    'name' => __("website/account_products/login-webmail"),
                ];

            return $buttons;
        }
        public function panel_links_for_admin(){
            $buttons                = [];

            if(method_exists($this,'use_adminArea_root_SingleSignOn'))
                $buttons["root_panel"] = [
                    'url'       => $this->area_link . "?operation=hosting_use_method&use_method=root_SingleSignOn",
                    'name'      => __("admin/products/login-root-panel"),
                ];

            if(method_exists($this,'use_adminArea_SingleSignOn'))
                $buttons["panel"] = [
                    'url'       => $this->area_link . "?operation=hosting_use_method&use_method=SingleSignOn",
                    'name'      => __("website/account_products/login-panel"),
                ];

            if(method_exists($this,'use_adminArea_webMail'))
                $buttons["mail"] = [
                    'url' => $this->area_link . "?operation=hosting_use_method&use_method=webMail",
                    'name' => __("website/account_products/login-webmail"),
                ];

            return $buttons;
        }

        public function use_adminArea_SingleSignOn()
        {
            return $this->panel_login();
        }
        public function use_adminArea_root_SingleSignOn()
        {
            return $this->root_panel_login();
        }
        public function use_clientArea_SingleSignOn()
        {
            return $this->panel_login();
        }

        private function panel_login(){
            $link       = $this->server["secure"] ? 'https://' : 'http://';
            $link       .= $this->server["ip"].":".$this->server["port"]."/api/loginAPI";
            $username   = $this->config["user"];
            $password   = $this->config["password"];
            $password_d = Crypt::decode($password,Config::get("crypt/user"));
            if($password_d) $password = $password_d;

            ?>
            <script type="text/javascript">
                setTimeout(function(){
                    var x = document.getElementById("RedirectForm").submit();
                },1);
            </script>
            <form enctype="application/x-www-form-urlencoded" action="<?php echo $link; ?>" method="POST" id="RedirectForm">
                <input type="hidden" name="username" value="<?php echo $username; ?>">
                <input type="hidden" name="password" value="<?php echo $password; ?>">
            </form>
            <?php

            return true;
        }
        private function root_panel_login(){
            $link       = $this->server["secure"] ? 'https://' : 'http://';
            $link       .= $this->server["ip"].":".$this->server["port"]."/api/loginAPI";
            $username   = $this->server["username"];
            $password   = $this->server["password"];
            ?>
            <script type="text/javascript">
                setTimeout(function(){
                    var x = document.getElementById("RedirectForm").submit();
                },1);
            </script>
            <form action="<?php echo $link; ?>" method="POST" id="RedirectForm">
                <input type="hidden" name="username" value="<?php echo $username; ?>">
                <input type="hidden" name="password" value="<?php echo $password; ?>">
            </form>
            <?php

            return true;
        }

    }

    Hook::add("ClientAreaEndBody",1,function(){
        if(Controllers::$init->getData("module") != "Panelica") return false;
        return '
<script type="text/javascript">
$(document).ready(function(){
    $("#get_details_module_content").css("display","none");
    $("#ftp_info_wrap").css("display","none");
});
</script>';
    });