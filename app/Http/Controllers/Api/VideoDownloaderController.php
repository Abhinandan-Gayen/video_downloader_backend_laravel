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


        $command = "yt-dlp --cookies cookies.txt --no-playlist --no-warnings -j " . escapeshellarg($url);

        $output = shell_exec($command);

        if (!$output) {
            return response()->json([
                'status' => 'error',
                'message' => 'Video fetch kora jayni. URL-ti support kore na ba terminal error ache.'
            ], 400);
        }

        $videoData = json_decode($output, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'status' => 'error',
                'message' => 'JSON parse error: ' . json_last_error_msg()
            ], 500);
        }

        $videos = [];
        $audios = [];

        if (isset($videoData['formats'])) {
            foreach ($videoData['formats'] as $format) {
                $ext = $format['ext'] ?? '';
                $vcodec = $format['vcodec'] ?? 'none';
                $acodec = $format['acodec'] ?? 'none';
                $url = $format['url'] ?? '';

                if ($vcodec === 'none' && $acodec !== 'none') {
                    $audios[] = [
                        'quality' => isset($format['abr']) ? round($format['abr']) . ' kbps' : 'Audio',
                        'format'  => $ext,
                        'url'     => $url
                    ];
                }

                if ($vcodec !== 'none') {
                    $quality = isset($format['height']) ? $format['height'] . 'p' : 'Unknown';
                    $hasAudio = ($acodec !== 'none');

                    $videos[] = [
                        'quality'   => $quality,
                        'format'    => $ext,
                        'has_audio' => $hasAudio,
                        'url'       => $url
                    ];
                }
            }
        }


        $videos = collect($videos)->unique(function ($item) {
            return $item['quality'] . $item['format'] . $item['has_audio'];
        })->sortByDesc(function ($item) {
            return (int) str_replace('p', '', $item['quality']);
        })->values()->toArray();

        $audios = collect($audios)->unique('quality')->sortByDesc(function ($item) {
            return (int) str_replace(' kbps', '', $item['quality']);
        })->values()->toArray();

        return response()->json([
            'status' => 'success',
            'data' => [
                'title'       => $videoData['title'] ?? 'Unknown Title',
                'thumbnail'   => $videoData['thumbnail'] ?? '',
                'duration'    => $videoData['duration'] ?? 0,
                'platform'    => $videoData['extractor'] ?? 'unknown',
                'downloads'   => [
                    'videos' => $videos, // 360p, 720p list
                    'audios' => $audios  // m4a, mp3 list
                ]
            ]
        ], 200);
    }
}
