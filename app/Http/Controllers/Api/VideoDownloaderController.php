<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class VideoDownloaderController extends Controller
{
    public function getVideoInfo(Request $request)
    {
        $request->validate([
            'url' => 'required|url'
        ]);

        $url = $request->url;

        // cookies path
        $cookiePath = base_path('cookies.txt');

        // yt-dlp command
        $command = "yt-dlp \
        --cookies " . escapeshellarg($cookiePath) . " \
        --user-agent 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36' \
        --extractor-args 'youtube:player_client=android' \
        --no-playlist \
        --dump-single-json \
        " . escapeshellarg($url) . " 2>&1";

        // execute command
        $output = shell_exec($command);

        // debug output check
        if (!$output) {
            return response()->json([
                'status' => 'error',
                'message' => 'No response from yt-dlp'
            ], 400);
        }

        // decode json
        $videoData = json_decode($output, true);

        // if json invalid
        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'error',
                'message' => 'yt-dlp error',
                'raw_output' => $output
            ], 500);
        }

        $videos = [];
        $audios = [];

        // formats extract
        if (isset($videoData['formats'])) {

            foreach ($videoData['formats'] as $format) {

                $ext = $format['ext'] ?? '';
                $vcodec = $format['vcodec'] ?? 'none';
                $acodec = $format['acodec'] ?? 'none';
                $downloadUrl = $format['url'] ?? '';

                // audio formats
                if ($vcodec === 'none' && $acodec !== 'none') {

                    $audios[] = [
                        'quality' => isset($format['abr'])
                            ? round($format['abr']) . ' kbps'
                            : 'Audio',

                        'format' => $ext,
                        'url' => $downloadUrl
                    ];
                }

                // video formats
                if ($vcodec !== 'none') {

                    $videos[] = [
                        'quality' => isset($format['height'])
                            ? $format['height'] . 'p'
                            : 'Unknown',

                        'format' => $ext,

                        'has_audio' => $acodec !== 'none',

                        'url' => $downloadUrl
                    ];
                }
            }
        }

        // unique + sort videos
        $videos = collect($videos)
            ->unique(function ($item) {
                return $item['quality'] .
                    $item['format'] .
                    $item['has_audio'];
            })
            ->sortByDesc(function ($item) {
                return (int) str_replace('p', '', $item['quality']);
            })
            ->values()
            ->toArray();

        // unique + sort audios
        $audios = collect($audios)
            ->unique('quality')
            ->sortByDesc(function ($item) {
                return (int) str_replace(' kbps', '', $item['quality']);
            })
            ->values()
            ->toArray();

        // final response
        return response()->json([
            'status' => 'success',

            'data' => [
                'title' => $videoData['title'] ?? 'Unknown Title',

                'thumbnail' => $videoData['thumbnail'] ?? '',

                'duration' => $videoData['duration'] ?? 0,

                'platform' => $videoData['extractor'] ?? 'unknown',

                'downloads' => [
                    'videos' => $videos,
                    'audios' => $audios
                ]
            ]
        ], 200);
    }
}
