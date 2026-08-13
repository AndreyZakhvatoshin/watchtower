<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckRequest;
use App\Modules\Checks\Contracts\CheckInterval;
use App\Modules\Checks\Contracts\CheckRepository;
use App\Modules\Checks\Contracts\CheckSnapshot;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Транспорт отделён от домена (AD-6): контроллер живёт в app/Http, а не внутри
 * модуля, и обращается к Checks только через CheckRepository.
 */
class CheckController extends Controller
{
    public function __construct(private readonly CheckRepository $checks) {}

    public function index(): View
    {
        return view('checks.index', [
            'checks' => $this->checks->all(),
        ]);
    }

    public function create(): View
    {
        return view('checks.create', [
            'intervals' => CheckInterval::cases(),
        ]);
    }

    public function store(CheckRequest $request): RedirectResponse
    {
        $check = $this->checks->create($request->toDraft());

        // Проверку человеку опознаёт адрес, а не ULID: идентификатор нужен
        // машине и лежит в таблице, в подтверждении он только шум.
        return redirect('/checks')->with('status', "Проверка {$check->url} заведена.");
    }

    public function edit(string $ulid): View
    {
        return view('checks.edit', [
            'check' => $this->find($ulid),
            'intervals' => CheckInterval::cases(),
        ]);
    }

    public function update(CheckRequest $request, string $ulid): RedirectResponse
    {
        $this->find($ulid);

        $check = $this->checks->update($ulid, $request->toDraft());

        return redirect('/checks')->with('status', "Проверка {$check->url} изменена.");
    }

    public function destroy(string $ulid): RedirectResponse
    {
        $check = $this->find($ulid);

        $this->checks->delete($ulid);

        return redirect('/checks')->with('status', "Проверка {$check->url} удалена.");
    }

    private function find(string $ulid): CheckSnapshot
    {
        return $this->checks->findByUlid($ulid) ?? abort(404);
    }
}
