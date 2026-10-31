<?php
declare(strict_types=1);

namespace app\mini\sample\service;

use app\common\exception\BusinessException;
use app\common\model\MiniProgram;
use app\common\support\MiniProgramContext;
use app\mini\sample\model\Feedback;
use app\mini\sample\model\Food;
use app\mini\sample\model\FoodBook;
use app\mini\sample\model\FoodDraw;
use app\mini\sample\model\FoodPool;
use think\Request;
use think\facade\Db;

final class FoodService
{
    private const DRAW_MODES = ['wheel', 'box'];
    private const FEEDBACK_TYPES = ['feature', 'bug', 'food_recommendation', 'other'];

    public function listFoods(Request $request): array
    {
        [$program, $userId] = $this->context($request);
        $page = $this->positiveInt($request->get('page', 1), 1, 100000);
        $pageSize = $this->positiveInt($request->get('page_size', 50), 1, 100);
        $query = Food::where('program_id', (int) $program->id)
            ->where('status', 1)
            ->order('sort', 'asc')
            ->order('id', 'asc');

        $total = (int) $query->count();
        $foods = $query->limit(($page - 1) * $pageSize, $pageSize)->select();
        $enabledIds = $this->enabledFoodIds((int) $program->id, $userId, true);

        $items = [];
        foreach ($foods as $food) {
            $items[] = $this->foodData($food, in_array((int) $food->id, $enabledIds, true));
        }

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function detail(Request $request, int $foodId): array
    {
        [$program] = $this->context($request);
        $food = $this->food((int) $program->id, $foodId, true);
        return $this->foodData($food, false, true);
    }

    public function getPool(Request $request): array
    {
        [$program, $userId] = $this->context($request);
        return Db::transaction(function () use ($program, $userId): array {
            $this->ensurePool((int) $program->id, $userId);
            $foods = Food::where('program_id', (int) $program->id)
                ->where('status', 1)
                ->order('sort', 'asc')
                ->order('id', 'asc')
                ->select();
            $enabledIds = $this->enabledFoodIds((int) $program->id, $userId, false);

            $items = [];
            foreach ($foods as $food) {
                $items[] = $this->foodData($food, in_array((int) $food->id, $enabledIds, true));
            }

            return [
                'enabled_food_ids' => $enabledIds,
                'enabled_count' => count($enabledIds),
                'total_count' => count($foods),
                'items' => $items,
            ];
        });
    }

    public function updatePool(Request $request, array $input): array
    {
        [$program, $userId] = $this->context($request);
        $foodIds = $this->foodIds($input['food_ids'] ?? null);
        if ($foodIds === []) {
            throw new BusinessException('奖池至少需要选择一项美食', 42201, 422);
        }

        $validCount = Food::where('program_id', (int) $program->id)
            ->where('status', 1)
            ->whereIn('id', $foodIds)
            ->count();
        if ((int) $validCount !== count($foodIds)) {
            throw new BusinessException('奖池包含无效或已停用的美食', 42200, 422);
        }

        Db::transaction(function () use ($program, $userId, $foodIds): void {
            FoodPool::where('program_id', (int) $program->id)
                ->where('user_id', $userId)
                ->delete();

            $now = date('Y-m-d H:i:s');
            FoodPool::insertAll(array_map(
                static fn (int $foodId): array => [
                    'program_id' => (int) $program->id,
                    'user_id' => $userId,
                    'food_id' => $foodId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                $foodIds
            ));
        });

        return $this->getPool($request);
    }

    public function draw(Request $request, array $input): array
    {
        [$program, $userId] = $this->context($request);
        $mode = trim((string) ($input['mode'] ?? ''));
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));
        if (!in_array($mode, self::DRAW_MODES, true)) {
            throw new BusinessException('抽取方式必须是 wheel 或 box', 42200, 422);
        }
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw new BusinessException('idempotency_key 必填且长度不能超过 128 个字符', 42200, 422);
        }

        return Db::transaction(function () use ($request, $program, $userId, $mode, $idempotencyKey): array {
            $existing = FoodDraw::where('program_id', (int) $program->id)
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->find();
            if ($existing) {
                return $this->drawData($existing, (int) $program->id);
            }

            $this->ensurePool((int) $program->id, $userId);
            $pool = FoodPool::where('program_id', (int) $program->id)
                ->where('user_id', $userId)
                ->select();
            if ($pool->isEmpty()) {
                throw new BusinessException('奖池至少需要选择一项美食', 42201, 422);
            }

            $selected = $pool[random_int(0, count($pool) - 1)];
            $food = $this->food((int) $program->id, (int) $selected->food_id, true);
            $draw = FoodDraw::create([
                'program_id' => (int) $program->id,
                'user_id' => $userId,
                'food_id' => (int) $food->id,
                'mode' => $mode,
                'idempotency_key' => $idempotencyKey,
                'request_id' => (string) $request->middleware('request_id'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $book = FoodBook::where('program_id', (int) $program->id)
                ->where('user_id', $userId)
                ->where('food_id', (int) $food->id)
                ->find();
            if ($book) {
                $book->save([
                    'draw_count' => (int) $book->draw_count + 1,
                    'last_drawn_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                FoodBook::create([
                    'program_id' => (int) $program->id,
                    'user_id' => $userId,
                    'food_id' => (int) $food->id,
                    'draw_count' => 1,
                    'first_drawn_at' => date('Y-m-d H:i:s'),
                    'last_drawn_at' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            return $this->drawData($draw, (int) $program->id);
        });
    }

    public function draws(Request $request): array
    {
        [$program, $userId] = $this->context($request);
        $page = $this->positiveInt($request->get('page', 1), 1, 100000);
        $pageSize = $this->positiveInt($request->get('page_size', 20), 1, 100);
        $query = FoodDraw::where('program_id', (int) $program->id)
            ->where('user_id', $userId)
            ->order('id', 'desc');
        $mode = trim((string) $request->get('mode', ''));
        if ($mode !== '') {
            if (!in_array($mode, self::DRAW_MODES, true)) {
                throw new BusinessException('抽取方式无效', 42200, 422);
            }
            $query->where('mode', $mode);
        }
        $total = (int) $query->count();
        $rows = $query->limit(($page - 1) * $pageSize, $pageSize)->select();
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->drawData($row, (int) $program->id);
        }

        return ['items' => $items, 'total' => $total, 'page' => $page, 'page_size' => $pageSize];
    }

    public function book(Request $request): array
    {
        [$program, $userId] = $this->context($request);
        $page = $this->positiveInt($request->get('page', 1), 1, 100000);
        $pageSize = $this->positiveInt($request->get('page_size', 20), 1, 100);
        $query = FoodBook::where('program_id', (int) $program->id)
            ->where('user_id', $userId)
            ->order('last_drawn_at', 'desc');
        $total = (int) $query->count();
        $rows = $query->limit(($page - 1) * $pageSize, $pageSize)->select();
        $items = [];
        foreach ($rows as $row) {
            $food = Food::where('program_id', (int) $program->id)->where('id', (int) $row->food_id)->find();
            if (!$food) {
                continue;
            }
            $items[] = [
                'food_id' => (int) $food->id,
                'name' => (string) $food->name,
                'emoji' => $food->emoji,
                'draw_count' => (int) $row->draw_count,
                'first_drawn_at' => $row->first_drawn_at,
                'last_drawn_at' => $row->last_drawn_at,
                'status' => (int) $food->status === 1 ? 'active' : 'inactive',
            ];
        }

        return [
            'items' => $items,
            'collected_count' => (int) FoodBook::where('program_id', (int) $program->id)->where('user_id', $userId)->count(),
            'total_food_count' => (int) Food::where('program_id', (int) $program->id)->where('status', 1)->count(),
            'total' => $total,
            'page' => $page,
            'page_size' => $pageSize,
        ];
    }

    public function bookDetail(Request $request, int $foodId): array
    {
        [$program, $userId] = $this->context($request);
        $book = FoodBook::where('program_id', (int) $program->id)
            ->where('user_id', $userId)
            ->where('food_id', $foodId)
            ->find();
        if (!$book) {
            throw new BusinessException('该美食尚未收录到图鉴', 40400, 404);
        }
        $food = $this->food((int) $program->id, $foodId, false);
        return array_merge($this->foodData($food, false, true), [
            'draw_count' => (int) $book->draw_count,
            'first_drawn_at' => $book->first_drawn_at,
            'last_drawn_at' => $book->last_drawn_at,
        ]);
    }

    public function stats(Request $request): array
    {
        [$program, $userId] = $this->context($request);
        $last = FoodDraw::where('program_id', (int) $program->id)
            ->where('user_id', $userId)
            ->order('id', 'desc')
            ->find();

        return [
            'draw_count' => (int) FoodDraw::where('program_id', (int) $program->id)->where('user_id', $userId)->count(),
            'book_count' => (int) FoodBook::where('program_id', (int) $program->id)->where('user_id', $userId)->count(),
            'total_food_count' => (int) Food::where('program_id', (int) $program->id)->where('status', 1)->count(),
            'last_drawn_at' => $last?->created_at,
        ];
    }

    public function feedback(Request $request, array $input): array
    {
        [$program, $userId] = $this->context($request);
        $type = trim((string) ($input['type'] ?? ''));
        $content = trim((string) ($input['content'] ?? ''));
        $foodName = trim((string) ($input['food_name'] ?? ''));
        $contact = trim((string) ($input['contact'] ?? ''));
        $idempotencyKey = trim((string) ($input['idempotency_key'] ?? ''));

        if (!in_array($type, self::FEEDBACK_TYPES, true)) {
            throw new BusinessException('反馈类型无效', 42200, 422);
        }
        if ($content === '' || mb_strlen($content) > 2000) {
            throw new BusinessException('反馈内容不能为空且不能超过 2000 个字符', 42200, 422);
        }
        if (mb_strlen($foodName) > 64 || mb_strlen($contact) > 128 || strlen($idempotencyKey) > 128) {
            throw new BusinessException('反馈字段长度超出限制', 42200, 422);
        }

        if ($idempotencyKey !== '') {
            $existing = Feedback::where('program_id', (int) $program->id)
                ->where('user_id', $userId)
                ->where('idempotency_key', $idempotencyKey)
                ->find();
            if ($existing) {
                return $this->feedbackData($existing);
            }
        }

        $record = Feedback::create([
            'program_id' => (int) $program->id,
            'user_id' => $userId,
            'type' => $type,
            'content' => $content,
            'food_name' => $foodName !== '' ? $foodName : null,
            'contact' => $contact !== '' ? $contact : null,
            'status' => 'pending',
            'idempotency_key' => $idempotencyKey !== '' ? $idempotencyKey : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $this->feedbackData($record);
    }

    private function context(Request $request): array
    {
        $program = MiniProgramContext::record($request);
        $user = $request->middleware('auth_user');
        $userId = is_object($user) ? (int) $user->id : 0;
        if ($userId < 1) {
            throw new BusinessException('登录用户上下文不存在', 40101, 401);
        }
        return [$program, $userId];
    }

    private function ensurePool(int $programId, int $userId): void
    {
        if (FoodPool::where('program_id', $programId)->where('user_id', $userId)->count() > 0) {
            return;
        }

        $foods = Food::where('program_id', $programId)->where('status', 1)->column('id');
        if ($foods === []) {
            throw new BusinessException('当前小程序还没有可用美食', 42201, 422);
        }
        $now = date('Y-m-d H:i:s');
        FoodPool::insertAll(array_map(
            static fn (mixed $foodId): array => [
                'program_id' => $programId,
                'user_id' => $userId,
                'food_id' => (int) $foodId,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $foods
        ));
    }

    private function enabledFoodIds(int $programId, int $userId, bool $initialize): array
    {
        if ($initialize) {
            $this->ensurePool($programId, $userId);
        }
        $poolIds = FoodPool::where('program_id', $programId)->where('user_id', $userId)->column('food_id');
        if ($poolIds === []) {
            return [];
        }
        return array_map(
            'intval',
            Food::where('program_id', $programId)
                ->where('status', 1)
                ->whereIn('id', $poolIds)
                ->column('id')
        );
    }

    private function food(int $programId, int $foodId, bool $active): Food
    {
        $query = Food::where('program_id', $programId)->where('id', $foodId);
        if ($active) {
            $query->where('status', 1);
        }
        $food = $query->find();
        if (!$food) {
            throw new BusinessException('美食不存在或已停用', 40400, 404);
        }
        return $food;
    }

    private function foodData(Food $food, bool $enabled, bool $detail = false): array
    {
        $data = [
            'id' => (int) $food->id,
            'name' => (string) $food->name,
            'emoji' => $food->emoji,
            'image_url' => $food->image_url ?: null,
            'description' => (string) $food->description,
            'enabled' => $enabled,
        ];
        if ($detail) {
            $data['origin'] = $food->origin;
            $data['steps'] = is_array($food->steps) ? $food->steps : [];
            $data['tip'] = $food->tip;
        }
        return $data;
    }

    private function drawData(FoodDraw $draw, int $programId): array
    {
        $food = $this->food($programId, (int) $draw->food_id, false);
        $book = FoodBook::where('program_id', $programId)
            ->where('user_id', (int) $draw->user_id)
            ->where('food_id', (int) $draw->food_id)
            ->find();
        return [
            'draw_id' => (int) $draw->id,
            'mode' => (string) $draw->mode,
            'food' => $this->foodData($food, true),
            'draw_count' => (int) FoodDraw::where('program_id', $programId)->where('user_id', (int) $draw->user_id)->count(),
            'book_count' => (int) FoodBook::where('program_id', $programId)->where('user_id', (int) $draw->user_id)->count(),
            'food_draw_count' => $book ? (int) $book->draw_count : 1,
            'drawn_at' => $draw->created_at,
        ];
    }

    private function feedbackData(Feedback $record): array
    {
        return [
            'feedback_id' => (int) $record->id,
            'status' => (string) $record->status,
            'created_at' => $record->created_at,
        ];
    }

    private function foodIds(mixed $value): array
    {
        if (!is_array($value)) {
            throw new BusinessException('food_ids 必须是数组', 42200, 422);
        }
        $ids = [];
        foreach ($value as $id) {
            if ((is_int($id) || (is_string($id) && ctype_digit($id))) && (int) $id > 0) {
                $ids[] = (int) $id;
                continue;
            }
            throw new BusinessException('food_ids 包含无效的美食 ID', 42200, 422);
        }
        return array_values(array_unique($ids));
    }

    private function positiveInt(mixed $value, int $default, int $max): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false || $number < 1) {
            return $default;
        }
        return min((int) $number, $max);
    }
}
