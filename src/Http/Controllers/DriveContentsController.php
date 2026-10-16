<?php

namespace OiLab\OiLaravelDrive\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OiLab\OiLaravelDrive\Data\DriveItemsData;
use OiLab\OiLaravelDrive\Data\DriveListingData;

class DriveContentsController extends Controller
{
    /**
     * The content of a folder (`folder`), or of the root of the drive.
     */
    public function contents(Request $request): DriveListingData
    {
        $folder = $this->folder($request, $request->query('folder'));
        $drive = $folder ? $this->drives->driveOf($folder) : $this->drive($request);

        return $this->drives->contents($drive, $folder);
    }

    public function search(Request $request): DriveItemsData
    {
        $request->validate(['q' => ['required', 'string', 'min:2', 'max:200']]);

        return $this->drives->search($this->drive($request), (string) $request->query('q'));
    }

    public function trash(Request $request): DriveItemsData
    {
        return $this->drives->trash($this->drive($request));
    }

    public function emptyTrash(Request $request): JsonResponse
    {
        return new JsonResponse(['deleted' => $this->drives->emptyTrash($this->drive($request, 'update'))]);
    }
}
