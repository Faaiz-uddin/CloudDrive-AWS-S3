<?php

namespace App\Http\Controllers\Api;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class S3Controller extends Controller
{

    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'folder' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');

            $disk = app()->environment('production') ? 's3' : 'local';

            $path = $file->store(
                $request->folder ? $request->folder : 'uploads',
                $disk
            );

            $url = $disk === 's3'
                ? Storage::disk('s3')->url($path)
                : url('storage/' . $path);

            $dbFile = File::create([
                'filename' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id() ?? null,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'File uploaded successfully!',
                'file' => $dbFile,
                'url' => $url
            ], 201);

        } catch (\Exception $e) {
            Log::error('Upload Error: ' . $e->getMessage());

            return response()->json([
                'status' => false,
                'message' => 'Upload failed!',
                'error' => $e->getMessage(), // dev/debug ke liye
            ], 500);
        }
    }
    public function download($filePath)
    {
        $filePath = urldecode($filePath);

        $disk = app()->environment('production') ? 's3' : 'local';

        if (!Storage::disk($disk)->exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'File not found'
            ], 404);
        }

        return Storage::disk($disk)->download($filePath);
    }

    public function getTemporaryUrl($filePath)
    {
        $filePath = urldecode($filePath); // spaces etc. handle karne ke liye

        if (!Storage::disk('s3')->exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'File not found',
                'path' => $filePath // debugging ke liye
            ], 404);
        }

        $url = Storage::disk('s3')->temporaryUrl($filePath, now()->addMinutes(10));

        return response()->json(['status' => true, 'url' => $url]);
    }

    public function delete($filePath)
    {

        $filePath = urldecode($filePath);

        $disk = app()->environment('production') ? 's3' : 'local';

        if (!Storage::disk($disk)->exists($filePath)) {
            return response()->json([
                'status' => false,
                'message' => 'File not found',
                'path' => $filePath
            ], 404);
        }

        Storage::disk($disk)->delete($filePath);

        return response()->json([
            'status' => true,
            'message' => 'File deleted successfully!',
            'path' => $filePath
        ]);
    }

    public function listFiles($folder = null)
    {
        $disk = app()->environment('production') ? 's3' : 'local';
        $folderPath = $folder ?? '';

        $data = $this->getFilesRecursive($disk, $folderPath);

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    private function getFilesRecursive($disk, $folder)
    {
        $result = [];


        $files = Storage::disk($disk)->files($folder);
        foreach ($files as $file) {
            $result[] = [
                'type' => 'file',
                'path' => $file,
                'url' => $disk === 's3' ? Storage::disk('s3')->url($file) : url('storage/'.$file)
            ];
        }


        $folders = Storage::disk($disk)->directories($folder);
        foreach ($folders as $subFolder) {
            $result[] = [
                'type' => 'folder',
                'path' => $subFolder,
                'children' => $this->getFilesRecursive($disk, $subFolder)
            ];
        }

        return $result;
    }

    public function move(Request $request)
    {
        $request->validate([
            'from' => 'required|string',
            'to' => 'required|string',
        ]);

        if (!Storage::disk('s3')->exists($request->from)) {
            return response()->json(['status' => false, 'message' => 'Source file not found'], 404);
        }

        Storage::disk('s3')->move($request->from, $request->to);

        return response()->json(['status' => true, 'message' => 'File moved successfully!']);
    }
}
