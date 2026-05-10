<?php
final class ItemRepository
{
    private function __construct()
    {
    }

    public static function searchAvailableItems(mysqli $connection, array $filters = []): mysqli_result
    {
        $where = ['i.QuantityAvailable > 0', "i.CurrentCondition <> 'Damaged'", "i.CurrentCondition <> 'Under Maintenance'"];
        $params = [];
        $types = '';

        $search = trim((string)($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(i.AssetNumber LIKE ? OR i.ItemName LIKE ? OR i.Category LIKE ? OR d.DepartmentName LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
            $types .= 'ssss';
        }

        $type = trim((string)($filters['type'] ?? ''));
        if ($type !== '' && in_array($type, ['Returnable', 'Reusable', 'Consumable'], true)) {
            $where[] = 'i.ItemType = ?';
            $params[] = $type;
            $types .= 's';
        }

        $condition = trim((string)($filters['condition'] ?? ''));
        if ($condition !== '' && in_array($condition, ['Good', 'Worn'], true)) {
            $where[] = 'i.CurrentCondition = ?';
            $params[] = $condition;
            $types .= 's';
        }

        $department = trim((string)($filters['department'] ?? ''));
        if ($department !== '') {
            $where[] = 'i.DepartmentID = ?';
            $params[] = $department;
            $types .= 's';
        }

        $qtySort = strtolower((string)($filters['qty_sort'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $sql = 'SELECT i.*, d.DepartmentName FROM Inventory_item i JOIN Department d ON i.DepartmentID=d.DepartmentID WHERE ' . implode(' AND ', $where) . " ORDER BY i.QuantityAvailable {$qtySort}, i.ItemName ASC";

        return self::runQuery($connection, $sql, $types, $params);
    }

    public static function listAdminInventory(mysqli $connection, string $orderBy, string $direction): mysqli_result
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $sql = "SELECT i.*, d.DepartmentName FROM Inventory_item i LEFT JOIN Department d ON i.DepartmentID=d.DepartmentID ORDER BY {$orderBy} {$direction}, i.AssetNumber ASC";
        return $connection->query($sql);
    }

    public static function findBorrowableItemForUpdate(mysqli $connection, string $assetNumber): ?array
    {
        $stmt = $connection->prepare('SELECT AssetNumber, ItemName, ItemType, CurrentCondition, QuantityAvailable FROM Inventory_item WHERE AssetNumber=? FOR UPDATE');
        $stmt->bind_param('s', $assetNumber);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public static function findReservationItemForUpdate(mysqli $connection, string $assetNumber): ?array
    {
        $stmt = $connection->prepare("SELECT i.AssetNumber, i.ItemName, i.QuantityAvailable,
                COALESCE(pending.PendingPenaltyUnits, 0) AS PendingPenaltyUnits,
                GREATEST(i.QuantityAvailable - COALESCE(pending.PendingPenaltyUnits, 0), 0) AS UsableStock
            FROM Inventory_item i
            LEFT JOIN (
                SELECT AssetNumber, SUM(QuantityMissing + QuantityDamaged) AS PendingPenaltyUnits
                FROM Reservation_breakage_report
                WHERE SettlementStatus = 'Pending'
                GROUP BY AssetNumber
            ) pending ON pending.AssetNumber = i.AssetNumber
            WHERE i.AssetNumber=? FOR UPDATE");
        $stmt->bind_param('s', $assetNumber);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    public static function listReservationCandidates(mysqli $connection, ?string $departmentId = null): mysqli_result
    {
        $where = ['i.QuantityAvailable > 0', "i.CurrentCondition <> 'Damaged'", "i.CurrentCondition <> 'Under Maintenance'"];
        $params = [];
        $types = '';

        if ($departmentId !== null && $departmentId !== '') {
            $where[] = 'i.DepartmentID = ?';
            $params[] = $departmentId;
            $types .= 's';
        }

        $sql = "SELECT i.*, d.DepartmentName,
                COALESCE(pending.PendingPenaltyUnits, 0) AS PendingPenaltyUnits,
                GREATEST(i.QuantityAvailable - COALESCE(pending.PendingPenaltyUnits, 0), 0) AS UsableStock
            FROM Inventory_item i
            JOIN Department d ON i.DepartmentID=d.DepartmentID
            LEFT JOIN (
                SELECT AssetNumber, SUM(QuantityMissing + QuantityDamaged) AS PendingPenaltyUnits
                FROM Reservation_breakage_report
                WHERE SettlementStatus = 'Pending'
                GROUP BY AssetNumber
            ) pending ON pending.AssetNumber = i.AssetNumber
            WHERE " . implode(' AND ', $where) . ' ORDER BY d.DepartmentName, i.ItemName';

        return self::runQuery($connection, $sql, $types, $params);
    }

    private static function runQuery(mysqli $connection, string $sql, string $types = '', array $params = []): mysqli_result
    {
        $stmt = $connection->prepare($sql);
        if ($params) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result();
    }
}
