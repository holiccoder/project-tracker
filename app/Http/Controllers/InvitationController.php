<?php

namespace App\Http\Controllers;

use App\Models\ProjectInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InvitationController extends Controller
{
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = ProjectInvitation::where('token', $token)->first();

        if (!$invitation) {
            return redirect()->route('dashboard')->with('error', '邀请链接无效');
        }

        if ($invitation->isExpired()) {
            return redirect()->route('dashboard')->with('error', '邀请链接已过期');
        }

        $user = auth('web')->user();

        if ($user) {
            if ($user->email !== $invitation->email) {
                return redirect()->route('dashboard')->with('error', '此邀请链接属于另一个邮箱账号');
            }
            // Already logged in
            $project = $invitation->project;
            if (!$project->hasMember($user)) {
                $project->members()->attach($user, ['role' => 'member']);
            }
            $invitation->delete();
            return redirect()->route('projects.show', $project->slug)->with('success', "成功加入项目：{$project->name}");
        }

        // Store token in session to associate upon registration or login
        session(['pending_invitation_token' => $token]);

        return redirect()->to('/')->with('success', '请登录您的账号以加入项目');
    }
}
