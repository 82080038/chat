<?php

declare(strict_types=1);

namespace Platform\PaperTrading;

use Platform\Core\BaseService;
use Platform\Core\Exceptions\ApiException;

final class PaperTradingService extends BaseService implements PaperTradingServiceInterface
{
    public function createAccount(array $data): array
    {
        $required = ['name', 'initial_cash'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required",
                    [$field => 'Required']
                );
            }
        }

        if ((float) $data['initial_cash'] <= 0) {
            throw new ApiException(
                422,
                'INVALID_CASH',
                'initial_cash must be positive'
            );
        }

        $id = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO paper_trading.paper_account
            (account_id, name, initial_cash, cash_balance, status, created_at)
            VALUES
            (:id, :name, :cash1, :cash2, :status, :now)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':name' => $data['name'],
            ':cash1' => $data['initial_cash'],
            ':cash2' => $data['initial_cash'],
            ':status' => 'ACTIVE',
            ':now' => $now,
        ]);

        return $this->getAccount($id);
    }

    public function getAccount(string $accountId): ?array
    {
        $sql = 'SELECT * FROM paper_trading.paper_account WHERE account_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $accountId]);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function placeOrder(string $accountId, array $data): array
    {
        $account = $this->getAccount($accountId);
        if ($account === null) {
            throw new ApiException(404, 'ACCOUNT_NOT_FOUND', 'Account not found');
        }

        if ($account['status'] !== 'ACTIVE') {
            throw new ApiException(403, 'ACCOUNT_INACTIVE', 'Account is not active');
        }

        $required = ['instrument_id', 'symbol', 'side', 'quantity'];
        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new ApiException(
                    422,
                    'VALIDATION_ERROR',
                    "Field {$field} is required",
                    [$field => 'Required']
                );
            }
        }

        $validSides = ['BUY', 'SELL'];
        if (!in_array($data['side'], $validSides, true)) {
            throw new ApiException(422, 'INVALID_SIDE', 'side must be BUY or SELL');
        }

        if ((float) $data['quantity'] <= 0) {
            throw new ApiException(422, 'INVALID_QTY', 'quantity must be positive');
        }

        $orderType = $data['order_type'] ?? 'MARKET';
        $validTypes = ['MARKET', 'LIMIT'];
        if (!in_array($orderType, $validTypes, true)) {
            throw new ApiException(
                422,
                'INVALID_ORDER_TYPE',
                'order_type must be MARKET or LIMIT'
            );
        }

        $price = $data['price'] ?? null;
        if ($orderType === 'LIMIT' && ($price === null || (float) $price <= 0)) {
            throw new ApiException(
                422,
                'INVALID_PRICE',
                'LIMIT order requires a positive price'
            );
        }

        $filledPrice = $orderType === 'MARKET'
            ? ($data['market_price'] ?? 10000)
            : (float) $price;
        $quantity = (float) $data['quantity'];
        $orderValue = $filledPrice * $quantity;

        if ($data['side'] === 'BUY') {
            if ((float) $account['cash_balance'] < $orderValue) {
                throw new ApiException(
                    422,
                    'INSUFFICIENT_CASH',
                    'Insufficient cash for this order'
                );
            }
        }

        $orderId = $this->uuid();
        $now = $this->now();

        $sql = 'INSERT INTO paper_trading.paper_order
            (order_id, account_id, instrument_id, symbol, side,
             order_type, quantity, price, filled_price, status,
             signal_id, created_at, filled_at)
            VALUES
            (:id, :acct, :inst, :sym, :side,
             :otype, :qty, :price, :fprice, :status,
             :signal, :now1, :now2)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id' => $orderId,
            ':acct' => $accountId,
            ':inst' => $data['instrument_id'],
            ':sym' => $data['symbol'],
            ':side' => $data['side'],
            ':otype' => $orderType,
            ':qty' => $quantity,
            ':price' => $price,
            ':fprice' => $filledPrice,
            ':status' => 'FILLED',
            ':signal' => $data['signal_id'] ?? null,
            ':now1' => $now,
            ':now2' => $now,
        ]);

        $this->updatePosition(
            $accountId,
            $data['instrument_id'],
            $data['symbol'],
            $data['side'],
            $quantity,
            $filledPrice
        );

        $this->updateCashBalance($accountId, $data['side'], $orderValue);

        return [
            'order_id' => $orderId,
            'account_id' => $accountId,
            'instrument_id' => $data['instrument_id'],
            'symbol' => $data['symbol'],
            'side' => $data['side'],
            'order_type' => $orderType,
            'quantity' => $quantity,
            'filled_price' => $filledPrice,
            'status' => 'FILLED',
            'signal_id' => $data['signal_id'] ?? null,
            'created_at' => $now,
        ];
    }

    public function cancelOrder(string $accountId, string $orderId): array
    {
        $sql = 'SELECT * FROM paper_trading.paper_order '
            . 'WHERE order_id = :id AND account_id = :acct';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $orderId, ':acct' => $accountId]);
        $order = $stmt->fetch();

        if ($order === false) {
            throw new ApiException(404, 'ORDER_NOT_FOUND', 'Order not found');
        }

        if ($order['status'] === 'FILLED') {
            throw new ApiException(
                409,
                'ORDER_ALREADY_FILLED',
                'Cannot cancel a filled order'
            );
        }

        $updateSql = 'UPDATE paper_trading.paper_order '
            . 'SET status = :status WHERE order_id = :id';
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute([':status' => 'CANCELLED', ':id' => $orderId]);

        return ['order_id' => $orderId, 'status' => 'CANCELLED'];
    }

    public function listOrders(string $accountId, int $page, int $perPage): array
    {
        [$page, $perPage, $offset] = $this->parsePagination(
            ['page' => $page, 'per_page' => $perPage]
        );

        $countSql = 'SELECT COUNT(*) FROM paper_trading.paper_order '
            . 'WHERE account_id = :acct';
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute([':acct' => $accountId]);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT * FROM paper_trading.paper_order '
            . 'WHERE account_id = :acct '
            . "ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':acct' => $accountId]);
        return $this->paginate($stmt->fetchAll(), $total, $page, $perPage);
    }

    public function getPositions(string $accountId): array
    {
        $sql = 'SELECT * FROM paper_trading.paper_position '
            . 'WHERE account_id = :acct AND quantity > 0 '
            . 'ORDER BY symbol ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':acct' => $accountId]);
        return $stmt->fetchAll();
    }

    public function getSummary(string $accountId): array
    {
        $account = $this->getAccount($accountId);
        if ($account === null) {
            throw new ApiException(404, 'ACCOUNT_NOT_FOUND', 'Account not found');
        }

        $positions = $this->getPositions($accountId);

        $countSql = 'SELECT COUNT(*) FROM paper_trading.paper_order '
            . 'WHERE account_id = :acct';
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute([':acct' => $accountId]);
        $totalOrders = (int) $countStmt->fetchColumn();

        $filledSql = 'SELECT COUNT(*) FROM paper_trading.paper_order '
            . 'WHERE account_id = :acct AND status = :status';
        $filledStmt = $this->db->prepare($filledSql);
        $filledStmt->execute([':acct' => $accountId, ':status' => 'FILLED']);
        $filledOrders = (int) $filledStmt->fetchColumn();

        return [
            'account' => $account,
            'open_positions' => count($positions),
            'positions' => $positions,
            'total_orders' => $totalOrders,
            'filled_orders' => $filledOrders,
            'initial_cash' => (float) $account['initial_cash'],
            'cash_balance' => (float) $account['cash_balance'],
        ];
    }

    public function validateSignal(string $signalId, string $accountId): array
    {
        $account = $this->getAccount($accountId);
        if ($account === null) {
            throw new ApiException(404, 'ACCOUNT_NOT_FOUND', 'Account not found');
        }

        $sql = 'SELECT * FROM paper_trading.paper_order '
            . 'WHERE signal_id = :signal AND account_id = :acct';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':signal' => $signalId, ':acct' => $accountId]);
        $existingOrders = $stmt->fetchAll();

        $alreadyTraded = count($existingOrders) > 0;

        return [
            'signal_id' => $signalId,
            'account_id' => $accountId,
            'already_traded' => $alreadyTraded,
            'existing_orders' => $existingOrders,
            'validation' => $alreadyTraded ? 'DUPLICATE' : 'OK',
        ];
    }

    private function updatePosition(
        string $accountId,
        string $instrumentId,
        string $symbol,
        string $side,
        float $quantity,
        float $price
    ): void {
        $sql = 'SELECT * FROM paper_trading.paper_position '
            . 'WHERE account_id = :acct AND instrument_id = :inst';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':acct' => $accountId, ':inst' => $instrumentId]);
        $existing = $stmt->fetch();

        if ($existing === false) {
            $id = $this->uuid();
            $now = $this->now();
            $insertSql = 'INSERT INTO paper_trading.paper_position
                (position_id, account_id, instrument_id, symbol,
                 quantity, avg_price, realized_pnl, created_at)
                VALUES
                (:id, :acct, :inst, :sym,
                 :qty, :price, 0, :now)';
            $insertStmt = $this->db->prepare($insertSql);
            $insertStmt->execute([
                ':id' => $id,
                ':acct' => $accountId,
                ':inst' => $instrumentId,
                ':sym' => $symbol,
                ':qty' => $side === 'BUY' ? $quantity : -$quantity,
                ':price' => $price,
                ':now' => $now,
            ]);
            return;
        }

        $currentQty = (float) $existing['quantity'];
        $currentAvg = (float) $existing['avg_price'];

        if ($side === 'BUY') {
            $newQty = $currentQty + $quantity;
            $newAvg = $newQty > 0
                ? (($currentQty * $currentAvg) + ($quantity * $price)) / $newQty
                : $price;
        } else {
            $newQty = $currentQty - $quantity;
            $realizedPnl = (float) $existing['realized_pnl'];
            if ($currentQty > 0) {
                $realizedPnl += ($price - $currentAvg) * min($quantity, $currentQty);
            }
            $newAvg = $newQty > 0 ? $currentAvg : 0;

            $updateSql = 'UPDATE paper_trading.paper_position
                SET quantity = :qty, avg_price = :avg,
                    realized_pnl = :pnl, updated_at = :now
                WHERE position_id = :id';
            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([
                ':qty' => $newQty,
                ':avg' => $newAvg,
                ':pnl' => $realizedPnl,
                ':now' => $this->now(),
                ':id' => $existing['position_id'],
            ]);
            return;
        }

        $updateSql = 'UPDATE paper_trading.paper_position
            SET quantity = :qty, avg_price = :avg, updated_at = :now
            WHERE position_id = :id';
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute([
            ':qty' => $newQty,
            ':avg' => $newAvg,
            ':now' => $this->now(),
            ':id' => $existing['position_id'],
        ]);
    }

    private function updateCashBalance(
        string $accountId,
        string $side,
        float $orderValue
    ): void {
        $account = $this->getAccount($accountId);
        $currentCash = (float) $account['cash_balance'];

        $newCash = $side === 'BUY'
            ? $currentCash - $orderValue
            : $currentCash + $orderValue;

        $sql = 'UPDATE paper_trading.paper_account '
            . 'SET cash_balance = :cash WHERE account_id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':cash' => $newCash, ':id' => $accountId]);
    }
}
