<?php
	putenv('TZ=PST8PDT');
	date_default_timezone_set('America/Los_Angeles');

	load_item_cache();

	$realm = $_GET['r'] ? $_GET['r'] : 'hyjal';
	$char = $_GET['n'] ? $_GET['n'] : 'bees';

	# you'll need to modify this if you're serving over HTTPS or on a weird port
	$self_url = 'http://'.$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];

	$guid_base = "{$realm}-{$char}-";

	$url = "http://us.battle.net/api/wow/character/{$realm}/{$char}?fields=feed";
	$profile_url = "http://us.battle.net/wow/en/character/{$realm}/{$char}/";

	$data = file_get_contents($url);
	$data = json_decode($data, true);


	$items = array();

	$html_realm = HtmlSpecialChars($data['realm']);
	$html_name = HtmlSpecialChars($data['name']);
	$name = "<a href=\"http://us.battle.net/wow/en/character/{$html_realm}/{$html_name}/\">{$html_name}</a>";

	foreach ($data['feed'] as $row){

		$item = array();
		$item['ts'] = substr($row['timestamp'], 0, -3);
		$item['guid'] = $guid_base.$row['timestamp'];

		if ($row['type'] == 'ACHIEVEMENT'){

			$title = HtmlSpecialChars($row['achievement']['title']);

			$item['url'] = "http://www.wowhead.com/achievement={$row['achievement']['id']}";
			$item['text'] = "{$name} earned the achievement <a href=\"{$item['url']}\">$title</a>";

			if ($row['achievement']['points']) $item['text'] .= " for {$row['achievement']['points']} points";

		}elseif ($row['type'] == 'BOSSKILL'){

			$title = HtmlSpecialChars($row['achievement']['title']);

			$item['url'] = "http://www.wowhead.com/achievement={$row['achievement']['id']}";
			$item['text'] = "{$name} got {$row['quantity']} {$title}";

		}elseif ($row['type'] == 'CRITERIA'){

			$step = HtmlSpecialChars($row['criteria']['description']);
			$title = HtmlSpecialChars($row['achievement']['title']);

			$item['url'] = "http://www.wowhead.com/achievement={$row['achievement']['id']}";
			$item['text'] = "{$name} completed step {$step} of achievement <a href=\"{$item['url']}\">$title</a>";

		}elseif ($row['type'] == 'LOOT'){

			$title = HtmlSpecialChars(get_item_name($row['itemId']));

			$item['url'] = "http://www.wowhead.com/item={$row['itemId']}";
			$item['text'] = "{$name} obtained <a href=\"{$item['url']}\">{$title}</a>";

		}else{

			$item['url'] = "";
			$item['text'] = "Unknown type in feed: {$row['type']}";
			$item['deets'] = $row;
		}

		$items[] = $item;
	}

	header("Content-type: application/rss+xml");

	echo '<'.'?xml version="1.0" encoding="UTF-8" ?'.">\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<title><? echo HtmlSpecialChars($char); ?></title>
	<description>WoW event feed for <?php echo HtmlSpecialChars($char); ?></description>
	<link><?php echo $profile_url; ?></link>
	<lastBuildDate><?php echo gmdate('r'); ?></lastBuildDate>
	<pubDate><?php echo gmdate('r'); ?></pubDate>
	<ttl>1800</ttl>
	<atom:link href="<?php echo HtmlSpecialChars($self_url); ?>" rel="self" type="application/rss+xml" />

<?php foreach ($items as $item){ ?>
	<item>
		<title><?php echo date('g:ia, D M jS', $item['ts']); ?></title>
		<description><?php echo HtmlSpecialChars($item['text']); ?></description>
		<link><?php echo HtmlSpecialChars($item['url']); ?></link>
		<guid isPermaLink="false"><?php echo $item['guid']; ?></guid>
		<pubDate><?php echo gmdate('r', $item['ts']); ?></pubDate>
	</item>
<?php } ?>

</channel>
</rss>


<?php


	function get_item_name($id){

		if (!$GLOBALS['item_cache'][$id]){

			$url = "http://us.battle.net/api/wow/item/{$id}";

			$data = file_get_contents($url);
			$data = json_decode($data, true);

			$GLOBALS['item_cache'][$id] = $data;
		}

		return $GLOBALS['item_cache'][$id]['name'];
	}

	function load_item_cache(){

		$GLOBALS['item_cache_file'] = dirname(__FILE__).'/item_cache.json';

		$GLOBALS['item_cache'] = array();

		if (file_exists($GLOBALS['item_cache_file'])){
			$data = file_get_contents($GLOBALS['item_cache_file']);
			$data = json_decode($data, true);
			$GLOBALS['item_cache'] = $data;
		}

		register_shutdown_function('save_item_cache');
	}

	function save_item_cache(){

		$fh = fopen($GLOBALS['item_cache_file'], 'w');
		fwrite($fh, json_encode($GLOBALS['item_cache']));
		fclose($fh);
	}
