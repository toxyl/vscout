<?php
    class URLScraper
    {
        static public function scrape(string $url) 
        {
            $url_raw  = $url;
            RandomDataGenerator::parse($url);
            $url      = preg_replace('/\s{1}/', '%20', $url);
            $ip       = Scout::ip($url);
            $ua       = RandomDataGenerator::random_user_agent();
            
            $output   = [];
            $output[] = "--------------------------------------------------------------------------------------------------------------------";
            $output[] = sprintf("User Agent:   %s", $ua);
            $output[] = "--------------------------------------------------------------------------------------------------------------------";
            $output[] = sprintf("URL:          %s", $url_raw);
            $output[] = sprintf("URL (parsed): %s", $url);
            $output[] = sprintf("IP:           %s", $ip ?? 'Does not compute!');
            $output[] = "--------------------------------------------------------------------------------------------------------------------";

            if ($ip)
            {
                $response = Scout::exec(sprintf("curl -i -v -q -s -A '%s' --compressed '%s' 2>&1", $ua, $url), true) ?? '';
                $lines    = explode("\n", $response);
                $status   = null;
                $location = $url;
                $reLoc    = '/^.*Location:\s+(http.*)\s*$/i';
                $reSta    = '/^.*(HTTP)\/(\d+\.\d+|\d+)\s+(\d+)\s*(.*)\s*$/i';
                $links    = [];
                
                preg_match_all('/href="(.*?)"/i', $response, $linksDoubleQuote, PREG_SET_ORDER);
                foreach ($linksDoubleQuote as &$lnd) 
                {
                    $links[] = "              " . urldecode(html_entity_decode($lnd[1]));
                }

                preg_match_all('/href=\'(.*?)\'/i', $response, $linksSingleQuote, PREG_SET_ORDER);
                foreach ($linksSingleQuote as &$lns) 
                {
                    $links[] = "              " . urldecode(html_entity_decode($lns[1]));
                }
                $links = array_unique($links);
                sort($links);
                $links = implode("\n", $links);

                foreach ($lines as $line) 
                {
                    if ($location != $url && $status != null)
                        break;

                    if (trim($line) == '') 
                        continue;

                    if (preg_match($reLoc, $line) === 1)
                    {
                        $location = preg_replace($reLoc, '$1', $line);
                        $output[] = sprintf("Location:     %s", $location);
                        continue;
                    }

                    if ($status == null && preg_match($reSta, $line) === 1)
                    {
                        $status = preg_replace($reSta, '$3 - $4', $line);
                        $output[] = sprintf("Status:       %s", $status);
                    }
                }
                $output[] = "Links:";
                $output[] = "$links";
                $output[] = "--------------------------------------------------------------------------------------------------------------------";
                $output[] = count($lines) . " lines of the response:";
                $output[] = "--------------------------------------------------------------------------------------------------------------------";
                foreach ($lines as $line) 
                {
                    $output[] = strip_tags($line);
                }
                $output[] = "--------------------------------------------------------------------------------------------------------------------";                
            }

            $output = implode("\n", $output);

            Response::text($output);
        } 
    }
