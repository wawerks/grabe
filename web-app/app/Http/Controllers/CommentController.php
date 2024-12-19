<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\LostItem;
use App\Models\FoundItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class CommentController extends Controller
{
    public function store(Request $request)
    {
        try {
            if (!auth()->check()) {
                Log::error('Unauthorized comment attempt - user not authenticated');
                return response()->json(['error' => 'Unauthorized'], 401);
            }
    
            Log::info('Comment request data:', $request->all());
            Log::info('Authenticated user:', ['user_id' => auth()->id(), 'user' => auth()->user()]);
    
            // Validate the request
            $validatedData = $request->validate([
                'item_type' => 'required|string|in:lost,found',
                'item_id' => ['required', 'integer', function ($attribute, $value, $fail) use ($request) {
                    $model = $request->item_type === 'lost' ? LostItem::class : FoundItem::class;
                    if (!$model::find($value)) {
                        $fail('The selected item does not exist.');
                    }
                }],
                'text' => 'required|string|max:500',
            ]);
    
            Log::info('Request validated successfully', ['data' => $validatedData]);
    
            // Determine the model based on item_type
            $modelClass = $request->item_type === 'lost' ? LostItem::class : FoundItem::class;
            Log::info('Using model class:', ['class' => $modelClass]);
    
            // Find the item
            $item = $modelClass::findOrFail($request->item_id);
            Log::info('Found item:', [
                'item' => $item->toArray(),
                'item_user_id' => $item->user_id,
                'current_user_id' => auth()->id()
            ]);
    
            DB::beginTransaction();
            try {
                // Create the comment using create method with fillable fields
                $comment = Comment::create([
                    'user_id' => auth()->id(),
                    'text' => $request->text,
                    'commentable_id' => $item->id,
                    'commentable_type' => get_class($item),
                    'item_type' => $request->item_type,
                    'item_id' => $request->item_id
                ]);
                
                Log::info('Comment created successfully:', ['comment' => $comment->toArray()]);

                // Load the user relationship for the response
                $comment->load('user');
                
                // Create notification for the item owner
                if ($item->user_id !== auth()->id()) {
                    try {
                        Log::info('Attempting to create notification:', [
                            'item_user_id' => $item->user_id,
                            'current_user_id' => auth()->id(),
                            'item_type' => $request->item_type,
                            'item_name' => $item->item_name,
                            'item_class' => get_class($item)
                        ]);

                        $notificationData = [
                            'title' => auth()->user()->name . ' commented on your item',
                            'message' => auth()->user()->name . ' commented on your ' . $request->item_type . ' item',
                            'item_id' => $item->id,
                            'item_type' => $request->item_type,
                            'comment_id' => $comment->id,
                            'item_name' => $item->item_name,
                            'commenter_name' => auth()->user()->name
                        ];

                        Log::info('Creating notification with data:', [
                            'user_id' => $item->user_id,
                            'type' => 'comment',
                            'data' => $notificationData
                        ]);

                        $notification = Notification::create([
                            'user_id' => $item->user_id,
                            'type' => 'comment',
                            'data' => $notificationData
                        ]);

                        Log::info('Notification created successfully:', $notification->toArray());
                    } catch (\Exception $e) {
                        Log::error('Failed to create notification:', [
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                            'item_user_id' => $item->user_id ?? 'unknown',
                            'current_user_id' => auth()->id() ?? 'unknown',
                            'item_type' => $request->item_type ?? 'unknown'
                        ]);
                    }
                } else {
                    Log::info('Skipping notification - user commenting on own item', [
                        'item_user_id' => $item->user_id,
                        'current_user_id' => auth()->id()
                    ]);
                }

                DB::commit();

                return response()->json([
                    'success' => true,
                    'comment' => [
                        'id' => $comment->id,
                        'text' => $comment->text,
                        'user' => [
                            'id' => $comment->user->id,
                            'name' => $comment->user->name
                        ],
                        'created_at' => $comment->created_at,
                        'updated_at' => $comment->updated_at,
                        'commentable_id' => $comment->commentable_id,
                        'commentable_type' => $comment->commentable_type,
                        'item_type' => $comment->item_type,
                        'item_id' => $comment->item_id
                    ]
                ]);
    
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error creating comment:', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json([
                    'error' => 'Failed to add comment',
                    'message' => $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            Log::error('Error in comment submission:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Failed to add comment',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $comment = Comment::with('user')->findOrFail($id);
            return response()->json($comment);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Comment not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $comment = Comment::findOrFail($id);

            // Check if the user owns the comment
            if ($comment->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $validatedData = $request->validate([
                'text' => 'required|string|max:500',
            ]);

            $comment->update($validatedData);
            return response()->json(['message' => 'Comment updated successfully', 'comment' => $comment]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update comment'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $comment = Comment::findOrFail($id);

            // Check if the user owns the comment
            if ($comment->user_id !== auth()->id()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $comment->delete();
            return response()->json(['message' => 'Comment deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete comment'], 500);
        }
    }

    // Fetch comments for a specific item with pagination
    public function index($item_type, $item_id)
    {
        try {
            Log::info('Fetching comments for item:', [
                'item_type' => $item_type,
                'item_id' => $item_id
            ]);

            // Determine the model based on item_type
            $modelClass = $item_type === 'lost' ? LostItem::class : FoundItem::class;

            // Find the item
            $item = $modelClass::findOrFail($item_id);
            
            Log::info('Found item:', [
                'id' => $item->id,
                'type' => $item_type,
                'model' => get_class($item)
            ]);

            // Get comments specifically for this item and type
            $comments = Comment::where('commentable_id', $item->id)
                ->where('commentable_type', get_class($item))
                ->with('user:id,name')
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Comments fetched:', [
                'count' => $comments->count(),
                'comments' => $comments->toArray()
            ]);

            $formattedComments = $comments->map(function($comment) {
                return [
                    'id' => $comment->id,
                    'text' => $comment->text,
                    'user' => [
                        'id' => $comment->user->id,
                        'name' => $comment->user->name
                    ],
                    'created_at' => $comment->created_at,
                    'updated_at' => $comment->updated_at,
                    'commentable_id' => $comment->commentable_id,
                    'commentable_type' => $comment->commentable_type,
                    'item_type' => $comment->item_type
                ];
            });

            return response()->json([
                'success' => true,
                'comments' => $formattedComments
            ]);

        } catch (\Exception $e) {
            Log::error('Error in fetching comments for item', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Internal Server Error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Fetch all comments from LostItem and FoundItem models with pagination
    public function view()
    {
        try {
            // Fetch comments with pagination for both LostItem and FoundItem models
            $lostItemComments = LostItem::with('comments.user:id,name')->get()->pluck('comments')->flatten();
            $foundItemComments = FoundItem::with('comments.user:id,name')->get()->pluck('comments')->flatten();

            // Merge the comments from both models
            $allComments = $lostItemComments->merge($foundItemComments)->sortByDesc('created_at');

            // Return the comments as a JSON response (could add pagination here)
            return response()->json(['comments' => $allComments->values()->all()]);
        } catch (\Exception $e) {
            Log::error('Error in fetching all comments', ['exception' => $e->getMessage()]);
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
    }
}
