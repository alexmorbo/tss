<?php

class Updater
{
	protected $_dir;
	protected $_urlsFile;

	public function __construct($dir, $file)
	{
		require_once __DIR__ . '/curler.php';
		$this->_dir = $dir . '/';
		$this->_urlsFile = $file;
	}


	public function run()
	{
		$torrents = explode(PHP_EOL, file_get_contents($this->_dir . $this->_urlsFile));

		foreach ($torrents as $torrentRaw) {
            $torrentData = explode('|', $torrentRaw);
            if ($torrentData[0] == 'rutor') {
                $this->_handleRutor($torrentData[1]);
            }
		}

        echo $this->_outputData($this->_rssData);
	}

    protected function _outputData(array $rssData)
    {
        $output = '<rss version="2.0">';
        $output .= '<channel>';
        $output .= '<title>TM</title>';
        $output .= '<link>http://tm</link>';
        $output .= '<ttl>15</ttl>';
        $output .= '<description>Fucking awesome</description>';

        foreach ($this->_rssData as $item) {
            /** @var DateTime $dt */
            $dt = $item['updated'];
            $output .= '<item>';
            $output .= '<title>' . htmlspecialchars($item['title']) .
                '</title>';
            $output .= '<link>' . $item['downloadUrl'] . '</link>';
            $output .= '<pubDate>' . $dt->format('r') . '</pubDate>';
            $output .= '<description>' . $item['description'] .
                '</description>';
            $output .= '<author>' . $item['author'] . '</author>';
            $output .= '</item>';
        }

        $output .= '</channel>';
        $output .= '</rss>';

        echo file_put_contents($this->_dir . 'rss.rss', $output);
    }


    protected $_rssData = [];

    protected function _addToRss(
        $torrentUrl, $downloadUrl, $description, $title, $author,
        \DateTime $updateDateTime
    )
    {
        $this->_rssData[] = [
            'torrentUrl' => $torrentUrl,
            'downloadUrl' => $downloadUrl,
            'title' => $title,
            'description' => $description,
            'updated' => $updateDateTime,
            'author' => $author,
        ];
    }


    protected function _handleRutor($torrentId)
    {
        $torrentUrl = 'http://alt.rutor.org/torrent/' . $torrentId;
        $downloadUrl = 'http://d.rutor.org/download/' . $torrentId;
        $page = Curler::getPage($torrentUrl);
        if ( ! empty($page)) {
            //ищем на странице дату регистрации торрента
            $pattern = '/<tr><td class=\"header\">Добавлен<\/td>' .
                '<td>(.+)  \((.+) назад\)<\/td><\/tr>/';
            if (! preg_match($pattern, $page, $torrentDate)) {
                return false;
            }
            //ищем название торрента
            $pattern = '/<title>(.+)<\/title>/';
            if (! preg_match($pattern, $page, $torrentInfo)) {
                return false;
            }

            $title = $torrentInfo[1];

            if (! isset($torrentDate[1])) {
                return false;
            }

            try {
                $updateDateTime = new \DateTime($torrentDate[1]);
            } catch (\Exception $e) {
                return false;
            }

            $this->_addToRss(
                $torrentUrl, $downloadUrl, $title, 'description', 'rutor',
                $updateDateTime
            );
        }
    }
}


(new Updater(__DIR__, 'urls.raw'))->run();