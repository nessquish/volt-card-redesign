# ВОЛЬТ — интернет-магазин электроники (PHP + SQLite)

Учебный проект [Zevs2006/feik-sait](https://github.com/Zevs2006/feik-sait) с переработанной карточкой товара. Весь остальной код оставлен как в исходнике.

## Что изменено

Карточка товара (`/product.php?id=1`):

- фото сверху, под ним название, оценка и краткие характеристики, справа блок покупки;
- вкладки «О товаре», «Характеристики», «Отзывы», «Доставка и возврат» переключаются на месте;
- способы получения с ценой и сроком рядом с кнопкой «В корзину».

Отзывы:

- таблица `reviews` в базе, рейтинг считается по отзывам;
- страница всех отзывов `/reviews.php?id=1` с фильтрами, сортировкой, кнопкой «Полезно» и формой «Написать отзыв».

Изменённые и новые файлы: `app/pages/product.php`, `app/pages/reviews.php`, `app/views/review-card.php`, `app/views/review-summary.php`, `app/views/header.php`, `app/functions.php`, `app/database.php`, `database/schema.sql`, `public/reviews.php`, `public/assets/css/product-v2.css`.

## Запуск

Нужен PHP 8.1+ с расширениями `pdo_sqlite` и `mbstring`.

```bash
cd public
php -S localhost:8000
```

Сайт откроется на http://localhost:8000, база создаётся при первом запуске.

Для входа: покупатель `user@test.local` / `User12345`, администратор `admin@test.local` / `Admin12345`.
