<?php
/*
 * By Miki Bán, 2020-10-18
 * Simple no-sql/no-store chat app 
 *
 * */

$url = "http".(!empty($_SERVER['HTTPS'])?"s":"")."://".$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

$room = isset($_REQUEST['room']) ? $_REQUEST['room'] : '';

session_start();
if (!isset($_SESSION['my_name'])) {
    $my_name = gen_name();
    $_SESSION['my_name'] = $my_name;
} else {
    $my_name = $_SESSION['my_name'];
}

if (!isset($_SESSION['room']))
    $_SESSION['room'] = array();

if (!isset($_SESSION['room'][$room]) and $room!='')
    $_SESSION['room'][$room] = array();

$mcache = new Memcached();
$mcache->addServer('localhost', 11211);


// send stored messages
if (isset($_GET['message'])) {
    if ($room!='') {
        $val = $mcache->get($room);
        if (preg_match('/^(\w+):::(\d+):::(.+)/',$val,$m)) {
            if ($m[1] != $my_name) {
                $_SESSION['room'][$room][$m[2]] = $val;
                $split = preg_split('/:::/m', $val);
                $m[3] = $split[2];
                $m[3] = preg_replace('/\n/','<br>',$m[3]);
                $m[3] = preg_replace('/^(https?:\/\/)(.+)/',"<a href='$0' target='_blank'>$0</a>",$m[3]);
                echo json_encode(array("id"=>$m[2],"name"=>$m[1],"message"=>$m[3]));
                exit;
            }
        }
    }
    echo json_encode(array(array("id"=>'',"name"=>'',"message"=>'')));
    exit;
}


if ($room != '') {
    if (isset($_POST['message'])) {
        
        $id = count($_SESSION['room'][$room]) + 1;
        $_SESSION['room'][$room][] = $my_name.':::'.$id.':::'.$_POST['message'];
        $mcache->set($room, $my_name.':::'.$id.':::'.$_POST['message'], 60);

        $_POST['message'] = preg_replace('/\n/','<br>',$_POST['message']);
        echo json_encode(array("id"=>$id,"name"=>$my_name,"message"=>$_POST['message']));
        exit;
    }
    elseif (isset($_POST['room'])) {
        $id = count($_SESSION['room'][$room]) + 1;
        $_SESSION['room'][$room][] = $my_name.':::'.$id.':::'.$my_name." entered";
        $mcache->set($room, $my_name.':::'.$id.':::'.$my_name." entered", 60);   
    }
}


?>
<!doctype html>
<html>
<meta charset="utf-8">
<header>
    <style>
        * {box-sizing: border-box;}
        #demo {max-width:100ch}
        .my-text {background-color: lightgreen;padding:2px}
        .remote-text{background-color: pink;padding:2px}
        .name {font-size:80%;padding-right:2em}
        .name:before {
            content: "\005B";
        }
        .name:after {
            content: "\005D";
        }
    </style>
</header>
<body>
<form method='post'>
Room name: <input name='room' value='<?=$room?>'><input type='submit' value='enter'> [Your are: <?=$my_name?>]
</form>
<hr>

<!--<button type="button" onclick="loadDoc()">update content</button>-->
<?php

    if ($room != '') {

?>
<h2>Messages</h2>
<div id='demo'>
<?php
foreach ($_SESSION['room'][$room] as $message) {

    if (preg_match('/^(\w+):::(\d+):::(.+)/m',$message,$m)) {
        $split = preg_split('/:::/m', $message);
        $m[3] = $split[2];
        $m[3] = preg_replace('/\n/','<br>',$m[3]);
        $m[3] = preg_replace('/^(https?:\/\/)(.+)/',"<a href='$0' target='_blank'>$0</a>",$m[3]);
        if ($m[1] == $my_name)
            echo "<div class='my-text' id='myid-".$m[2]."'>".$m[3]."</div>";
        else
            echo "<div class='remote-text' id='id-".$m[1].$m[2]."'><span class='name'>". $m[1] ."</span>".$m[3]."</div>";
    }
    echo "<hr style='margin:0;border-top:1px solid #fff;border-bottom:none'>";
}
?>
</div>
<hr>
<form method='post' id='messageForm' style='width:20em;height:4em'>
    <input type='hidden' name='room' value='<?=$room?>'>
    <textarea id='message' name='message' style='width:100%;height:100%'></textarea><br>
    <input type='submit' style='width:100%' value='submit'>
</form>
<br>
<br>
</body>

<script>
window.addEventListener( "load", function () {
  function sendData() {
    const XHR = new XMLHttpRequest();

    // Bind the FormData object and the form element
    const FD = new FormData( form );

    // Define what happens on successful data submission
    XHR.addEventListener( "load", function(event) {
        var m = JSON.parse(event.target.responseText);
        if (m.id!==undefined  && m.id != '') { 
            if (document.getElementById("id-" + m.name + m.id) == null) {
                document.getElementById("demo").innerHTML +=
                    "<div class='my-text' id='myid-" + m.name + m.id + "'>" + m.message + "</div><hr style='margin:0;border-top:1px solid #fff;border-bottom:none'>";
            }
        }
        document.getElementById("message").value = "";
    } );

    // Define what happens in case of error
    XHR.addEventListener( "error", function( event ) {
      alert( 'Oops! Something went wrong.' );
    } );

    // Set up our request
    XHR.open( "POST", "<?=$url?>" );

    // The data sent is what the user provided in the form
    XHR.send( FD );
  }
 
  // Access the form element...
  const form = document.getElementById( "messageForm" );

  // ...and take over its submit event.
  form.addEventListener( "submit", function ( event ) {
    event.preventDefault();

    sendData();
  } );
} );
var interval = setInterval(loadDoc, 2000);
function loadDoc() {
  var xhttp = new XMLHttpRequest();
  xhttp.onreadystatechange = function() {
    if (this.readyState == 4 && this.status == 200) {
        var m = JSON.parse(this.responseText);
        if (m.id!==undefined  && m.id != '') { 
            if (document.getElementById("id-" + m.name + m.id) == null) {
                document.getElementById("demo").innerHTML +=
                    "<div class='remote-text' id='id-" + m.name + m.id + "'><span class='name'>" + m.name + "</span>" + m.message + "</div><hr style='margin:0;border-top:1px solid #fff;border-bottom:none'>";
            }
        }
    }
  };
  xhttp.open("GET", "chat.php?room=<?=$room?>&message", true);
  xhttp.send();
}
</script>
<?php
    }
?>
</html>

<?php
function readable_random_string($length = 6) {

    $conso=array("b","br","c","d","dt","dv","f","fr","g","h","j","jn","k","kr","l","lcs",
                 "m","n","ncs","p","pr","r","s","sr","t","v","w","x","y","z","sz","szk","zs","cs","gy","ny","ly");

    $vocal=array("a","e","i","o","u","io","ea");

    $password="";

    srand ( (double)microtime()*1000000 );

    $max = $length/2;

    for($i=1; $i<=$max; $i++)
    {

      $password .= $conso[rand(0,count($conso)-1)];

      $password .= $vocal[rand(0,count($vocal)-1)];

    }

    return $password;
}
function clean($n) {
    if (!preg_match('/[-\[\]]/',$n))
        return $n;
}

function gen_name () {
    
    $pspell_link = pspell_new("hu");
    
    for ($i = 0; $i<2; $i++) {
        $a = "";
        $w = readable_random_string(6);

        if (!pspell_check($pspell_link, $w)) {
            $suggestions = pspell_suggest($pspell_link, $w);
            $s2 = array_filter(array_map("clean",$suggestions));
            
            if (count($s2)) {
                $k = array_rand($s2,1);
                $w = $s2[$k];
            }
        }
        $a .= $w;
        $a = ucfirst($a);
        $pw_pieces[] = $a;
    }
    return implode($pw_pieces);

}
?>
