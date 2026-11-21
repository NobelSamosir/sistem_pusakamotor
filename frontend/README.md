# Frontend Pusaka Motor (React + Vite)

Jalankan (butuh Node.js 20.19+ atau 22.12+):

    npm install
    npm run dev

Buka http://localhost:5173

Backend harus menyala: `php artisan serve` (port 8000) dan layanan model `uvicorn main:app --port 8001`.
Permintaan /api otomatis diteruskan ke Laravel oleh proxy Vite (vite.config.js).
