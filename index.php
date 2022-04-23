<?php 
  
    ob_start();
    set_time_limit(0);
    session_cache_expire(0);
    session_start();
    error_reporting(0);

    $baseURL = 'http://en.wikipedia.org';
    $url = $baseURL.'/wiki/Mobile_Network_Code';
    $htmlContent = file_get_contents($url);
		
	$dom = new DOMDocument();
	$dom->loadHTML($htmlContent);
	$dom->preserveWhiteSpace = false;

    $tables = $dom->getElementsByTagName('table');
    $rows = $tables->item(1)->getElementsByTagName('tr');

    $tabel = array();
    $tempData = array();
    $cache = array();

    $cachetime = 60 * 60 * 1 * 24;
    $cachefile = 'cache.json';
    $cachecreate = false;

    if(file_exists($cachefile) && time() - $cachetime < filemtime($cachefile)){
      
        $tabel = json_decode(file_get_contents($cachefile),1);
   
    }else { 
        
        $cachecreate = true;

        foreach ($rows as $k=>$row) {

            $cols = $row->getElementsByTagName('td');

            if(!empty($cols) && count($cols) > 0) {
                
                $tempKey = md5(trim($cols->item(1)->textContent));
                if(isset($tempData[$tempKey]) || empty(trim($cols->item(1)->textContent))) continue;   

                $tabel[$k]['countryName'] = trim($cols->item(1)->textContent);
                $tabel[$k]['countryCode'] = trim($cols->item(2)->textContent);
                $tempData[$tempKey] = $tabel[$k]['countryName'];
              
                $links = $row->getElementsByTagName('a');
                
                foreach ($links as $link) { 
                    
                    if($link->getAttribute('href') && $link->getAttribute('class') == 'mw-redirect') {
                        
                        $countryRows = array();
                        $countryUrl = $baseURL.$link->getAttribute('href');
                        $countryId = explode('#',$countryUrl)[1];
                        $countryUrl = str_replace('#'.$countryId,'',$baseURL.$link->getAttribute('href'));
                        
                        if(!isset($cache[md5($countryUrl)])) {
                            $cache[md5($countryUrl)] = file_get_contents($countryUrl);
                        }

                        $htmlCountryContent = $cache[md5($countryUrl)];  
                        $htmlCountryContentById = explode($countryId,$htmlCountryContent)[1];
                    
                        $dom->loadHTML($htmlCountryContentById);
                        $countryTables = $dom->getElementsByTagName('table');
                        
                        if(!is_null($countryTables->item(0))){
                            $countryRows = $countryTables->item(0)->getElementsByTagName('tr');
                        }
                    
                        foreach ($countryRows as $i => $countryRow) {
                            $countryCols = $countryRow->getElementsByTagName('td');

                            if(!empty($countryCols) && count($countryCols) > 0) { 

                                $tabel[$k]['MCC'][$i] = trim($countryCols->item(0)->textContent);
                                $tabel[$k]['MNC'][$i] = trim($countryCols->item(1)->textContent);
                                $tabel[$k]['brand'][$i] = trim($countryCols->item(2)->textContent);
                                $tabel[$k]['operator'][$i] = trim($countryCols->item(3)->textContent);    
                                $tabel[$k]['bands'][$i] = trim($countryCols->item(4)->textContent);
                            }
                                
                        }  
                    
                    }
                    
                }  

            }   
        }    
    }

    if($cachecreate)
        file_put_contents($cachefile, json_encode($tabel));
    
?>
<!DOCTYPE html>
<html>
    <head>
        <title>test</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">
        <style>
            body {

                font-size: 14pt;
            }
            
            * {

                box-sizing: border-box;       
            }

            select {

                padding: 1rem;
                background: #fefefe;
                border: 1px solid #bbb;
                width: 100%;
                font-size: 1rem;
            }

            .select-wrap {

                display: flex;
                flex-flow: row nowrap;
            }

            .select-wrap > div{

                width: 100%;
                flex-grow: 1;
                padding: .5rem;
            }

            .country-content {

                padding: 2rem;
                font-size: 1rem;
                line-height: 1.5;
            }
            
        </style>
    </head>
    <body>
        <div class="select-wrap">  

            <div class="choose-country">
                <select id="country">
                    <option value="">- choose country -</option>    
                    <? foreach ($tabel as $key=>$value) {
                        echo '<option value="'. $key .'">'. $value['countryName'] .'</option>';
                    }?>
                </select>
            </div><div class="choose-mnc">
                <select id="mnc" disabled>
                    <option value="">- choose MNC -</option>    
                    
                </select>
            </div>

        </div>

        <div class="content"></div>

        <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js" type="text/javascript"></script>

        <script>

            $(document).ready(function(){
                    
                var data = <? echo json_encode($tabel);?>;
                var tempData = '';

                $('select#country').on('change', function(){
                    var obj = $(this);
                    $('select#mnc').attr('disabled', true).find('option').not(':first').remove();
                    $('.content').html('');
                    tempData = '';
                    
                    if(obj.val()) {

                        tempData = data[obj.val()];
                        var options = tempData['MNC'];

                        $.each(options, function(i,v){
                            $('select#mnc').append($('<option>', { 
                                value: i,
                                text : v 
                            }));
                        });

                        $('select#mnc').removeAttr('disabled').html();
                    }
                });

                $('select#mnc').on('change', function(){
                    var obj = $(this);
                    var html = '';

                    if(obj.val()) { 
                        html += '<div class="country-content">';
                        $.each(tempData, function(i,v){
                            
                            if(i == 'countryCode' || i == 'countryName') 
                                html +='<strong>'+i+':</strong> '+tempData[i]+'<br>';
                            else  
                            html += '<strong>'+i+':</strong> '+tempData[i][obj.val()]+'<br>';  
                        });
                        html += '</div>';
                    }

                    $('.content').html(html);

                });

            });

        </script>
     </body>
</html>
