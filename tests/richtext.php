<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
use App\{RichText,HttpError};
$checks=0;
function check(bool $ok,string $label):void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;echo "PASS $label\n";}
$path='/media/images/'.str_repeat('a',40).'.png';
$safe=RichText::sanitize('<h2>หัวข้อ</h2><p><strong>หนา</strong><em>เอียง</em><u>ใต้</u><s>ทับ</s> H<sub>2</sub> x<sup>2</sup></p>');
check(str_contains($safe,'<h2>หัวข้อ</h2>')&&str_contains($safe,'<sub>2</sub>')&&str_contains($safe,'<sup>2</sup>'),'formatting and Thai text survive');
foreach(['script','iframe','object','svg','math','style','template','form'] as $tag){$html=RichText::sanitize('<p>keep</p><'.$tag.'>attack</'.$tag.'>');check($html==='<p>keep</p>','discard dangerous '.$tag);}
$safe=RichText::sanitize('<p onclick="alert(1)" id="unsafe" class="ql-align-center evil" style="color:rgb(12, 34, 56);position:fixed;background-image:url(https://example.invalid)">safe</p>');
check($safe==='<p class="ql-align-center" style="color: rgb(12, 34, 56)">safe</p>','allow only known classes and color styles');
foreach(['javascript:alert(1)','java&#x0a;script:alert(1)','data:text/html,bad','//example.invalid','/\\example.invalid'] as $href)check(!str_contains(RichText::sanitize('<a href="'.$href.'">link</a>'),'href='),'remove unsafe URL '.$href);
check(str_contains(RichText::sanitize('<a href="https://example.com" target="_blank" onclick="bad()">link</a>'),'rel="noopener noreferrer"'),'external links isolate opener');
foreach(['data:image/png;base64,AAAA','https://example.com/p.png','/media/images/fake.svg','/media/images/../bad.png',$path.'?x=1'] as $src)check(RichText::sanitize('<img src="'.$src.'">')==='','reject noncanonical image URL');
check(RichText::sanitize('<img onerror="bad()" src="'.$path.'" alt="A &amp; B">')==='<img src="'.$path.'" alt="A &amp; B">','canonical image and escaped alt survive');
check(RichText::imagePaths('<img src="'.$path.'"><img src="'.$path.'">')===[basename($path)],'image references are deduplicated basenames');
check(RichText::render("<b>literal</b>\nline",'text')==="&lt;b&gt;literal&lt;/b&gt;<br />\nline",'legacy plaintext never becomes HTML');
$input='<p class="ql-align-right"><a href="https://example.com?a=1&amp;b=2">link</a><img src="'.$path.'" alt="&quot;"></p>';
check(RichText::sanitize(RichText::sanitize($input))===RichText::sanitize($input),'sanitization is idempotent');
foreach([['<p><br></p>','html',true,200],['<p>&nbsp;​</p>','html',true,200],['','bad',false,200],['12345','text',false,4],["\xff",'html',false,200]] as [$text,$format,$required,$max]){try{RichText::prepare($text,$format,0,$required,$max);check(false,'invalid content accepted');}catch(HttpError $e){check($e->getCode()===422,'invalid or empty content rejected');}}
check(RichText::prepare('<script>literal</script>','text',0,true)==='<script>literal</script>','plaintext is stored unchanged');
echo "All $checks rich text checks passed.\n";
