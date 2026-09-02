<?php

namespace App\Http\Controllers;

use App\Models\Proposal;
use App\Services\AccessService;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProposalController extends Controller
{
    public function index(Request $request): View
    {
        $query = Proposal::query()->orderBy('data_geracao', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('tipo')) {
            $query->where('tipo', $request->string('tipo')->toString());
        }

        return view('proposals.index', [
            'proposals' => $query->limit(300)->get(),
        ]);
    }

    public function approvals(Request $request): View
    {
        $role = $request->user()->normalizedRole();
        $isApprover = in_array($role, ['admin', 'head_comercial'], true);

        return view('proposals.approvals', [
            'proposals' => Proposal::query()->where('status', 'pending_approval')->orderBy('data_geracao', 'desc')->limit(300)->get(),
            'isApprover' => $isApprover,
        ]);
    }

    public function approve(Request $request, string $id, ActivityLogger $logger): RedirectResponse
    {
        abort_unless(in_array($request->user()->normalizedRole(), ['admin', 'head_comercial'], true), 403);

        $proposal = Proposal::query()->findOrFail($id);
        $proposal->fill([
            'status' => 'approved',
            'approved_by' => $request->user()->username ?? $request->user()->email,
            'approved_at' => now(),
            'rejection_reason' => null,
        ])->save();

        $logger->log('Proposta aprovada', ['proposal_code' => $proposal->proposal_code]);
        return back()->with('success', 'Proposta aprovada.');
    }

    public function reject(Request $request, string $id, ActivityLogger $logger): RedirectResponse
    {
        abort_unless(in_array($request->user()->normalizedRole(), ['admin', 'head_comercial'], true), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $proposal = Proposal::query()->findOrFail($id);
        $proposal->fill([
            'status' => 'rejected',
            'approved_by' => $request->user()->username ?? $request->user()->email,
            'approved_at' => now(),
            'rejection_reason' => $data['reason'],
        ])->save();

        $logger->log('Proposta rejeitada', ['proposal_code' => $proposal->proposal_code, 'reason' => $data['reason']]);
        return back()->with('success', 'Proposta rejeitada.');
    }
}
