<?php

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $databaseDir = dirname(DB_PATH);
    if (!is_dir($databaseDir)) {
        mkdir($databaseDir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');

    $hasProducts = $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'products'")->fetchColumn();
    if (!(int) $hasProducts) {
        initialize_database($pdo);
    }

    return $pdo;
}

function initialize_database(PDO $pdo): void
{
    $schema = file_get_contents(APP_ROOT . '/database/schema.sql');
    $seed = file_get_contents(APP_ROOT . '/database/seed.sql');

    if ($schema === false || $seed === false) {
        throw new RuntimeException('Не удалось загрузить файлы инициализации базы данных.');
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($schema);
        $pdo->exec($seed);
        seed_accounts($pdo);
        seed_orders($pdo);
        seed_reviews($pdo);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function seed_accounts(PDO $pdo): void
{
    $accounts = [
        [
            'full_name' => 'Илья Волков',
            'email' => 'admin@test.local',
            'phone' => '+7 913 204-77-10',
            'address' => 'г. Новосибирск, Красный проспект, д. 25, офис 4',
            'password' => 'Admin12345',
            'role' => 'admin',
            'created_at' => '2026-01-12 09:00:00',
        ],
        [
            'full_name' => 'Анна Смирнова',
            'email' => 'user@test.local',
            'phone' => '+7 913 555-21-84',
            'address' => 'г. Новосибирск, ул. Ленина, д. 42, кв. 17',
            'password' => 'User12345',
            'role' => 'customer',
            'created_at' => '2026-02-03 14:20:00',
        ],
        [
            'full_name' => 'Мария Ковалёва',
            'email' => 'maria@test.local',
            'phone' => '+7 923 118-40-52',
            'address' => 'г. Бердск, ул. Первомайская, д. 8, кв. 3',
            'password' => 'Maria12345',
            'role' => 'customer',
            'created_at' => '2026-03-21 11:10:00',
        ],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO users (full_name, email, phone, address, password_hash, role, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($accounts as $account) {
        $stmt->execute([
            $account['full_name'],
            $account['email'],
            $account['phone'],
            $account['address'],
            password_hash($account['password'], PASSWORD_DEFAULT),
            $account['role'],
            $account['created_at'],
        ]);
    }
}

function seed_orders(PDO $pdo): void
{
    $stmt = $pdo->prepare('SELECT id, email FROM users');
    $stmt->execute();
    $users = [];
    foreach ($stmt->fetchAll() as $user) {
        $users[$user['email']] = (int) $user['id'];
    }

    $orders = [
        [
            'number' => 'TM-2026-0418',
            'user_id' => $users['user@test.local'],
            'full_name' => 'Анна Смирнова',
            'phone' => '+7 913 555-21-84',
            'email' => 'user@test.local',
            'address' => 'г. Новосибирск, ул. Ленина, д. 42, кв. 17',
            'delivery' => 'courier',
            'payment' => 'card_online',
            'comment' => 'Позвонить за час до доставки.',
            'subtotal' => 16980,
            'delivery_cost' => 590,
            'total' => 17570,
            'status' => 'completed',
            'created_at' => '2026-04-18 14:05:00',
            'items' => [
                [11, 'AeroBuds Pro', 12990, 1],
                [21, 'PowerCore 20000', 3990, 1],
            ],
        ],
        [
            'number' => 'TM-2026-0731',
            'user_id' => $users['user@test.local'],
            'full_name' => 'Анна Смирнова',
            'phone' => '+7 913 555-21-84',
            'email' => 'user@test.local',
            'address' => 'г. Новосибирск, ул. Ленина, д. 42, кв. 17',
            'delivery' => 'pickup',
            'payment' => 'card_online',
            'comment' => '',
            'subtotal' => 99990,
            'delivery_cost' => 0,
            'total' => 99990,
            'status' => 'shipped',
            'created_at' => '2026-07-31 10:40:00',
            'items' => [
                [2, 'Nebula Book Pro 16', 99990, 1],
            ],
        ],
        [
            'number' => 'TM-2026-1012',
            'user_id' => $users['user@test.local'],
            'full_name' => 'Анна Смирнова',
            'phone' => '+7 913 555-21-84',
            'email' => 'user@test.local',
            'address' => 'г. Новосибирск, ул. Ленина, д. 42, кв. 17',
            'delivery' => 'express',
            'payment' => 'card_courier',
            'comment' => 'Домофон 42В.',
            'subtotal' => 15980,
            'delivery_cost' => 990,
            'total' => 16970,
            'status' => 'processing',
            'created_at' => '2026-10-12 18:25:00',
            'items' => [
                [16, 'Titan Mechanical TKL', 9990, 1],
                [17, 'Titan Mouse Pro', 5990, 1],
            ],
        ],
    ];

    $orderStmt = $pdo->prepare(
        'INSERT INTO orders
         (order_number, user_id, full_name, phone, email, address, delivery_method, payment_method, comment, subtotal, delivery_cost, total, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $itemStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
         VALUES (?, ?, ?, ?, ?, ?)'
    );

    foreach ($orders as $order) {
        $orderStmt->execute([
            $order['number'],
            $order['user_id'],
            $order['full_name'],
            $order['phone'],
            $order['email'],
            $order['address'],
            $order['delivery'],
            $order['payment'],
            $order['comment'],
            $order['subtotal'],
            $order['delivery_cost'],
            $order['total'],
            $order['status'],
            $order['created_at'],
        ]);
        $orderId = (int) $pdo->lastInsertId();

        foreach ($order['items'] as $item) {
            [$productId, $name, $unitPrice, $quantity] = $item;
            $itemStmt->execute([
                $orderId,
                $productId,
                $name,
                $unitPrice,
                $quantity,
                $unitPrice * $quantity,
            ]);
        }
    }
}


function seed_reviews(PDO $pdo): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO reviews (product_id, author, rating, pros, cons, comment, photos, helpful, store_reply, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    // Подробные отзывы на Aurora Air 14: [товар, автор, оценка, достоинства, недостатки, комментарий, фото, «полезно», ответ магазина, дата]
    $items = [
        [1, 'Игорь К.', 5, 'экран OLED, вес, почти не слышно кулер', 'не нашёл', 'Взял для работы. Батареи хватает на рабочий день, экран яркий. Пришёл на следующий день.', 2, 31, null, '2026-11-14 12:20:00'],
        [1, 'Марина С.', 5, 'лёгкий, помещается в любую сумку', '', 'Windows уже установлена, без лишнего софта. Очень довольна покупкой.', 1, 18, null, '2026-11-09 18:05:00'],
        [1, 'Алексей Р.', 4, 'быстро работает, яркий экран', 'тихие динамики, глянцевый экран бликует', 'За свои деньги отличный вариант. Для видеозвонков динамиков хватает, для музыки лучше взять наушники.', 0, 9, 'Алексей, спасибо за отзыв! Для музыки рекомендуем наушники AeroBuds Pro — они есть в наличии.', '2026-11-02 10:40:00'],
        [1, 'Дмитрий В.', 3, 'тонкий и лёгкий', 'заметно нагревается под нагрузкой', 'Для офисных задач подходит, в играх и монтаже нагрев ощутим.', 0, 6, null, '2026-10-27 21:15:00'],
        [1, 'Анна П.', 1, '', 'на крышке была царапина', 'Пришёл с царапиной на крышке.', 1, 4, 'Анна, приносим извинения. Мы заменили ноутбук в течение двух дней — спасибо, что дали нам возможность исправить ситуацию.', '2026-10-21 09:30:00'],
    ];

    // Короткие отзывы, чтобы набралось 24: 16 пятёрок и 3 четвёрки (итого 18 / 4 / 1 / 0 / 1).
    $names = ['Сергей Т.', 'Ольга М.', 'Никита Б.', 'Елена Ж.', 'Павел Л.', 'Татьяна Н.', 'Максим Г.', 'Юлия Ш.', 'Роман Ф.', 'Виктория Д.',
              'Андрей З.', 'Наталья К.', 'Артём С.', 'Ирина В.', 'Кирилл О.', 'Светлана Р.', 'Денис П.', 'Алёна Е.', 'Владимир Ч.'];
    $good = ['Отличный ноутбук за свои деньги.', 'Экран очень приятный, цвета яркие.', 'Лёгкий и тихий, заряд держит весь день.', 'Быстро доставили, всё в порядке.',
             'Пользуюсь месяц, нареканий нет.', 'Работает быстро, не греется.', 'Рекомендую, брали второй такой же.', 'Клавиатура удобная, печатать приятно.'];
    foreach ($names as $i => $name) {
        $rating = in_array($i, [3, 9, 15], true) ? 4 : 5;
        $items[] = [1, $name, $rating, '', '', $good[$i % count($good)], 0, $i % 7, null, date('Y-m-d H:i:s', strtotime('2026-10-18 12:00:00') - $i * 86400)];
    }

    // Отзывы на другие товары; там, где отзывов нет, страница показывает пустое состояние.
    $items[] = [13, 'Олег Д.', 5, 'шумоподавление, звук', '', 'В метро почти не слышно шума. Звук насыщенный, батареи хватает на неделю.', 1, 14, null, '2026-11-05 19:10:00'];
    $items[] = [13, 'Ирина Л.', 5, 'комфортные амбушюры', '', 'Могу носить несколько часов, уши не устают.', 0, 5, null, '2026-10-30 11:00:00'];
    $items[] = [13, 'Тимур А.', 4, 'звук, шумоподавление', 'тяжеловаты', 'Отличные наушники, но к весу нужно привыкнуть.', 0, 3, 'Тимур, спасибо! Вес 250 г — рекомендуем делать перерывы при длительном ношении.', '2026-10-12 15:45:00'];
    $items[] = [6, 'Кристина М.', 5, 'камера, экран', '', 'Фото на камеру получаются отличные, экран плавный.', 2, 11, null, '2026-11-11 13:25:00'];
    $items[] = [6, 'Вадим Н.', 4, 'батарея', 'нет чехла в комплекте', 'Заряд держит почти два дня, но чехол пришлось докупать.', 0, 2, null, '2026-11-03 17:00:00'];

    foreach ($items as $row) {
        $stmt->execute($row);
    }
}
