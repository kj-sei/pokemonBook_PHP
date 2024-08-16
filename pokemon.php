<?php
function getCacheContents($url, $cachePath, $cacheLimit = 86400)
{
    if (file_exists($cachePath) && filemtime($cachePath) + $cacheLimit > time()) {
        // キャッシュ有効期間内なのでキャッシュの内容を返す
        return file_get_contents($cachePath);
    } else {
        // キャッシュがないか、期限切れなので取得しなおす
        $data = file_get_contents($url);
        file_put_contents($cachePath, $data, LOCK_EX); // キャッシュに保存
        return $data;
    }
}

function getItems($url, $name)
{
    $res = getCacheContents($url, "./cache/{$name}cache");
    return json_decode($res, true);
}

function getImage($url, $name)
{
    $res = getCacheContents($url, "./image/{$name}.png");
    return $res;
}

function main()
{
    //$ページングした時のページを格納（初期値として１を代入）
    if (!isset($_POST["sel_page"])) {
        $sel_page = 1;
    } else {
        $sel_page = $_POST["sel_page"];
    }

    //$表示する件数の値を格納（初期値として10を代入）
    if (!isset($_POST["one_page"])) {
        $one_page = 10;
    } else {
        $one_page = $_POST["one_page"];
    }

    //ページングした時の表示する件数の値を格納
    if (isset($_POST["select_page"])) {
        $one_page = $_POST["select_page"];
    }
    $colum_length = 1010; //表示するデータの件数
    $page = $colum_length / $one_page; //ページ数を取得
    $page = ceil($page); // 整数に直す。
    $now_page = ($sel_page - 1) * $one_page; // OFFSET を取得 ページ数 -1 * 20

    /** PokeAPI のデータを取得する(id=11から20のポケモンのデータ) */
    $url = "https://pokeapi.co/api/v2/pokemon/?limit={$one_page}&offset={$now_page}";
    //OFFSET を取得 ページ数 -1 * 20
    $now_page = ($sel_page - 1) * $one_page;
    $data = getItems($url, "data" . $one_page . "," . $now_page);
    //フレキシブルボックスで表示
    selectbox($one_page);
    echo "<div class='flex'>";
    foreach ($data['results'] as $value) {
        //オフセットの範囲のポケモンデータを取得
        // var_dump($value["name"]);
        $datas = getItems($value["url"], "pokemon" . $value["name"]);
        //idからspeciesのデータを取得
        $url2 = "https://pokeapi.co/api/v2/pokemon-species/{$datas['id']}/";
        $species = getItems($url2, "species" . $datas["id"]);
        // echo "<pre>";
        // var_dump($species["flavor_text_entries"]);
        if(isset($species["names"][0]["name"])){
            $name_jpn = $species["names"][0]["name"];
        }else{
            $name_jpn = "名前が見つかりません";
        }
        if(isset($species["flavor_text_entries"])){
            foreach ($species["flavor_text_entries"] as $key_species => $value_species) {
                if($value_species["language"]["name"] == "en"){
                    $description = $value_species["flavor_text"];
                    break;
                }
            }
            foreach ($species["flavor_text_entries"] as $key_species_j => $value_species_j) {
                if($value_species_j["language"]["name"] == "ja-Hrkt"){
                    $description_jpn = $value_species_j["flavor_text"];
                    break;
                }
            }
        }
        if(!isset($description)){
            $description = "Sorry.We could not find the description.";
        }

        if(!isset($description_jpn)){
            $description_jpn = "申し訳ございません。説明文が見当たりませんでした。";
        }

        //画像の取得
        if(isset($datas['sprites']['front_default'])){
            getImage($datas['sprites']['front_default'], "front_image" . $datas["id"]);
            $front_image = "./image/front_image{$datas["id"]}.png";
        }else{
            $front_image = "./style/no_image.png";
        }

        if(isset($datas['sprites']['back_default'])){
            getImage($datas['sprites']['back_default'], "back_image" . $datas["id"]);
            $back_image = "./image/back_image{$datas["id"]}.png";
        }else{
            $back_image = "./style/no_image.png";
        }
        
        //タイプのデータを取得(コンマ区切りで取得する)
        $type = "";
        $type_japanese = "";
        foreach ($datas["types"] as $key2 => $value2) {
            if ($key2 == 0) {
                $front_color = type_color($value2["type"]["name"]);
                $back_color = $front_color;
            } else {
                $back_color = type_color($value2["type"]["name"]);
            }

            $type .= $value2["type"]["name"];
            $type_url = $value2["type"]["url"];
            $type_japanese_data = getItems($type_url, "types" . $value2["type"]["name"]);
            $type_japanese .= $type_japanese_data["names"][0]["name"];
            if ($key2 < count($datas["types"]) - 1) {
                $type .= ",";
                $type_japanese .= "、";
            }
        }
        card($front_color, $value, $datas, $type, $species, $back_color, $type_japanese,$description,$description_jpn,$name_jpn, $front_image, $back_image);
    }
    echo "</div>";
    paging_button($sel_page, $one_page, $page);

}

function card($front_color, $value, $datas, $type, $species, $back_color, $type_japanese, $description, $description_jpn,$name_jpn, $front_image, $back_image)
{
    $japan_weight = $datas["weight"]/10;
    $japan_height = $datas["height"]/10;

    //カード形式でポケモンの情報を表示（カードをホバーすると裏返る。表は英語の情報、裏は日本語の情報を表示する）
    echo <<<_FORM_
    <div class="card">
        <div class="back">
            <div class="l-wrapper_02 card-radius_02">
                <article class="card_02 card_02_front" style="background-color: {$front_color};">
                    <div class="card__header_02">
                    <p class="card__title_02">{$value["name"]}</p>
                    <figure class="card__thumbnail_02 card__thumbnail_02_front">
                        <img src={$front_image} class="image_size">
                    </figure>
                    </div>
                    <div class="card__body_02">
                    <p class="card__text2_02">
                    <p><b>height：</b>{$datas["height"]}</p>
                    <p><b>weight：</b>{$datas["weight"]}</p>
                    <p><b>type：</b>{$type}</p>
                    <p><b>description:</b>{$description}</p>
                    </p>
                    </div>    
                </article>
            </div>
        </div>
        <div class="front">
            <div class="l-wrapper_02 card-radius_02">
                <article class="card_02 card_02_back" style="background-color: {$back_color};">
                    <div class="card__header_02">
                    <p class="card__title_02">{$name_jpn}</p>
                    <figure class="card__thumbnail_02 card__thumbnail_02_back">
                        <img src={$back_image} class="image_size">
                    </figure>
                    </div>
                    <div class="card__body_02">
                    <p class="card__text2_02">
                    <p><b>高さ：</b>{$japan_height}[m]</p>
                    <p><b>重さ：</b>{$japan_weight}[kg]</p>
                    <p><b>タイプ：</b>{$type_japanese}</p>
                    <p><b>説明:</b>{$description_jpn}</p>
                    </p>
                    </div>    
                </article>
            </div>
        </div>
    </div>
    _FORM_;
}
function paging_button($sel_page, $one_page, $page)
{
    // ページング機能の実装
    echo "<div class='paging'>";
    //前へボタンの実装
    if ($sel_page > 1) {
        $backpage = $sel_page - 1;
    } else {
        $backpage = 1;
    }
    $nextpage = $sel_page + 1;
    echo "
    <form action='pokemon.php' method='post'>
    <input type='hidden' name='sel_page' value='1'>
    <input type='hidden' name='select_page' value='{$one_page}'>
    <input type='submit' class='other_btn' value='＜＜' class='paging'>
    </form>
    ";

    echo "
    <form action='pokemon.php' method='post'>
    <input type='hidden' name='sel_page' value='{$backpage}'>
    <input type='hidden' name='select_page' value='{$one_page}'>
    <input type='submit' class='other_btn' value='＜' class='paging'>
    </form>
    ";
    //数字ボタンの実装
    $count = 0;
    for ($i = $sel_page - 5; $i <= $sel_page + 5; $i++) {
        //現在のページの時は黄色でそれ以外は水色で表示する
        if ($i == $sel_page) {
            $button = "now_btn";
        } else {
            $button = "other_btn";
        }
        if($i > 0  && $i <= $page){
            echo "
            <form action='pokemon.php' method='post'>
                <input type='hidden' name='sel_page' value='{$i}'>
                <input type='hidden' name='select_page' value='{$one_page}'>
                <input type='submit' class='{$button}' value='{$i}' class='paging'>
            </form>
            ";
        }

    }
    //次へボタンの実装
    if ($sel_page < $page) {
        $nextpage = $sel_page + 1;
    } else {
        $nextpage = $page;
    }
    echo "
    <form action='pokemon.php' method='post'>
    <input type='hidden' name='sel_page' value='{$nextpage}'>
    <input type='hidden' name='select_page' value='{$one_page}'>
    <input type='submit' class='other_btn' value='＞' class='paging'>
    </form>
    ";
    echo "
    <form action='pokemon.php' method='post'>
    <input type='hidden' name='sel_page' value='{$page}'>
    <input type='hidden' name='select_page' value='{$one_page}'>
    <input type='submit' class='other_btn' value='＞＞' class='paging'>
    </form>
    ";
    echo "</div>";

}

//セレクトボックスの実装（現在の表示件数が先頭に来るようになっている）
function selectbox($one_page)
{
    echo "<div style='display: inline'>";
    if ($one_page == 10) {
        echo <<<_FORM_
        <form action='pokemon.php' method='post'>
            <select name="one_page" onchange="this.form.submit()">
                <option value="10">10ページ</option>
                <option value="20">20ページ</option>
                <option value="50">50ページ</option>
            </select>
        </form>
        _FORM_;
    } elseif ($one_page == 20) {
        echo <<<_FORM_
        <form action='pokemon.php' method='post'>
            <select name="one_page" onchange="this.form.submit()">
                <option value="20">20ページ</option>
                <option value="10">10ページ</option>
                <option value="50">50ページ</option>
            </select>
        </form>
        _FORM_;
    } else {
        echo <<<_FORM_
        <form action='pokemon.php' method='post'>
            <select name="one_page" onchange="this.form.submit()">
                <option value="50">50ページ</option>
                <option value="10">10ページ</option>
                <option value="20">20ページ</option>
            </select>
        </form>
        _FORM_;
    }
    echo "</div>";
}


function type_color($type)
{
    switch ($type) {
        case "normal":
            $color = "#848d97";
            break;
        case "grass":
            $color = "#55b14b";
            break;
        case "poison":
            $color = "#a958c5";
            break;
        case "fire":
            $color = "#fe9847";
            break;
        case "flying":
            $color = "#8da9df";
            break;
        case "water":
            $color = "#4e97d6";
            break;
        case "bug":
            $color = "#9dbf2b";
            break;
        case "electric":
            $color = "#f3d042";
            break;
        case "ground":
            $color = "#d27646";
            break;
        case "fairy":
            $color = "#e983e0";
            break;
        case "fighting":
            $color = "#ce3956";
            break;
        case "ice":
            $color = "#68c6b8";
            break;
        case "psychic":
            $color = "#f76b70";
            break;
        case "rock":
            $color = "#c2b181";
            break;
        case "steel":
            $color = "#498b98";
            break;
        case "ghost":
            $color = "#5260ae";
            break;
        case "dragon":
            $color = "#0765b7";
            break;
        case "dark":
            $color = "#50495a";
            break;
    }
    return $color;
}
?>



<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="style/style.css" rel="stylesheet">
    <title>ポケモンずかん　勢井</title>
</head>

<body>
    <header>
        <div class="label"></div>
        <h1>ポケモンずかん</h1>
        <div class="base">
            <div class="center">
                <button class="center-button"></button>
            </div>
        </div>
        <div class="shadow"></div>
    </header>
    <main>
        <?php main(); ?>
    </main>
</body>

</html>