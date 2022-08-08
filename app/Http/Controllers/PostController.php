<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ConvertVideoForDownloading;

class PostController extends Controller
{
    public function index(Post $post, Request $request)
    {
        return Inertia::render('Posts/Index', [
            'posts' => Post::query()       
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn($post) => [
                'id'            =>  $post->id,
                'name'          =>  $post->user->name,
                'username'      =>  $post->user->username,
                'title'         =>  $post->title,
                'description'   =>  $post->description,
                'time'          =>  $post->created_at->diffForHumans(),
                'avatar'        =>  $post->user->getProfilePhotoUrlAttribute(),
                'userlink'      =>  '@' . $post->user->username,
                'media'         =>  'storage/' . $post->files,
                'video'         =>  Storage::disk('public')->url('uploads/' . $post->user->id . '/' . 'videos/' . $post->id . '.mp4'),
                'delete'        =>  Auth::user()->id === $post->user_id,
                'status'        =>  $post->status
            ])
        ]);
    }

    public function show(Post $post)
    {
        return Inertia::render('Posts/Show', [
            'post' => [
                'id'                =>  $post->id,
                'description'       =>  $post->description,
                'avatar'            =>  $post->user->getProfilePhotoUrlAttribute(),
                'time'              =>  $post->created_at->diffForHumans(),
                'username'          =>  $post->user->username,
                'video'             =>  Storage::disk('public')->url('uploads/' . $post->user->id . '/' . 'videos/' . $post->id . '.mp4'),
            ]
            ]);
    }

    public function store(Request $request)
    {
        $attributes = $request->validate([
            'description'   =>  'required|min:1',
            'status'        =>  'required',
            'nsfw'          =>  'nullable|boolean',
            'video'         =>  'required|file|mimetypes:video/x-ms-asf,video/x-flv,video/mp4,video/mpeg,application/x-mpegURL,video/MP2T,video/3gpp,video/quicktime,video/x-msvideo,video/x-ms-wmv,video/avi|max:10240',
        ]);

        // if($request->hasFile('files')) 
        // {
        //    $attributes['files'] = $request->file('files')->store('uploads/images/', 'public');
        // }

        $attributes['user_id'] = auth()->user()->id;

        $storeURL = Str::random(16);

        // $attributes['video'] = $request->file('uploads/' . $request['user_id'] . '/' . 'videos/' . $storeURL, 'public');
        
        $attributes = Post::create([
            'user_id'       =>  auth()->user()->id,
            'status'        =>  $request->status,
            'is_nsfw'       =>  $request->nsfw,
            'disk'          =>  'public',
            'original_name' =>  $request->file('video')->getClientOriginalName(),
            'path'          =>  $request->file('video')->store('uploads/' . $request['user_id'] . '/' . 'videos/' . $storeURL, 'public'),
            'description'   =>  $request->description
        ]);

        $this->dispatch(new ConvertVideoForDownloading($attributes));
        
        return redirect(('/home'));
    }

    // Delete item
    public function destroy(Post $post) 
    {
        if (! Gate::allows('delete-post', $post)) {
            abort(403);
        }

        File::delete(public_path('uploads/videos/') . $post->id . '.mp4');

        // Delete the file
        File::delete($post->path);

        $post->delete();
        return redirect('/home')->with('message', 'Post deleted successfully.');
    }
}
