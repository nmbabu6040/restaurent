<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Supplier;
use App\Support\ExpenseCategories;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
            'category' => ['nullable', Rule::in(array_keys(ExpenseCategories::all()))],
        ]);

        $month = Carbon::createFromFormat('Y-m-d', ($filters['month'] ?? now()->format('Y-m')) . '-01')->startOfMonth();
        $inMonth = fn() => Expense::query()->whereBetween('expense_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()]);

        return view('admin.expenses.index', [
            'month' => $month,
            'categories' => ExpenseCategories::all(),
            'total' => (float) $inMonth()->sum('amount'),
            'byCategory' => $inMonth()->selectRaw('category, SUM(amount) as total')->groupBy('category')->pluck('total', 'category')->map(fn($t) => (float) $t)->all(),
            'expenses' => $inMonth()
                ->when($filters['category'] ?? null, fn($q, $c) => $q->where('category', $c))
                ->with('supplier')
                ->orderByDesc('expense_date')->orderByDesc('id')
                ->paginate(25)->withQueryString(),
        ]);
    }

    public function create()
    {
        return view('admin.expenses.form', [
            'expense' => new Expense(['expense_date' => now()->toDateString()]),
            'categories' => ExpenseCategories::all(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        Expense::create($request->validate($this->rules()) + ['user_id' => $request->user()->id]);

        return redirect()->route('admin.expenses.index')->with('status', 'Expense saved.');
    }

    public function edit(Expense $expense)
    {
        return view('admin.expenses.form', [
            'expense' => $expense,
            'categories' => ExpenseCategories::all(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($request->validate($this->rules()));

        return redirect()->route('admin.expenses.index', ['month' => $expense->expense_date->format('Y-m')])->with('status', 'Expense updated.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return back()->with('status', 'Expense deleted.');
    }

    private function rules(): array
    {
        return [
            'expense_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'category' => ['required', Rule::in(array_keys(ExpenseCategories::all()))],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999', 'decimal:0,2'],
            'description' => ['nullable', 'string', 'max:255'],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')],
        ];
    }
}
