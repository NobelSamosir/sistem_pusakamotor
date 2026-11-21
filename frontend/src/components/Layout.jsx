import { NavLink, Outlet, useNavigate } from 'react-router-dom'
import { useAuth } from '../lib/auth'
import useFetch from '../lib/useFetch'

const menu = [
  { group: 'Utama', items: [['/', 'Dashboard'], ['/motor', 'Data Motor']] },
  { group: 'Transaksi', items: [['/pembelian', 'Pembelian'], ['/biaya', 'Biaya Kendaraan'], ['/penjualan', 'Penjualan'], ['/beban', 'Beban Operasional']] },
  { group: 'DSS', items: [['/prediksi', 'Prediksi Harga'], ['/rekomendasi', 'Rekomendasi Harga']] },
  { group: 'Akuntansi', items: [['/laporan', 'Laporan'], ['/jurnal', 'Jurnal Umum']] },
  { group: 'Lainnya', items: [['/master', 'Data Master']] },
]

export default function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()
  const { data: health } = useFetch('/ml/health')

  const keluar = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="flex min-h-screen">
      <aside className="no-print fixed inset-y-0 left-0 flex w-60 flex-col bg-slate-900 text-slate-300">
        <div className="flex items-center gap-3 px-5 py-5">
          <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-teal-600 text-sm font-bold text-white">PM</div>
          <div>
            <p className="font-semibold text-white">Pusaka Motor</p>
            <p className="text-xs text-slate-400">SIA dan DSS</p>
          </div>
        </div>
        <nav className="flex-1 overflow-y-auto px-3 pb-4">
          {menu.map((g) => (
            <div key={g.group} className="mt-4">
              <p className="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{g.group}</p>
              {g.items.map(([to, label]) => (
                <NavLink
                  key={to}
                  to={to}
                  end={to === '/'}
                  className={({ isActive }) =>
                    `mt-1 block rounded-lg px-3 py-2 text-sm ${isActive ? 'bg-teal-700 text-white' : 'hover:bg-slate-800 hover:text-white'}`}
                >
                  {label}
                </NavLink>
              ))}
            </div>
          ))}
        </nav>
        <div className="border-t border-slate-800 px-5 py-4 text-xs">
          <p className="flex items-center gap-2">
            <span className={`h-2 w-2 rounded-full ${health?.online ? 'bg-emerald-400' : 'bg-rose-400'}`} />
            Layanan model {health?.online ? 'aktif' : 'tidak aktif'}
          </p>
          <p className="mt-3 truncate text-slate-400">{user?.name}</p>
          <button onClick={keluar} className="mt-2 text-teal-400 hover:text-teal-300">Keluar</button>
        </div>
      </aside>
      <main className="print-full ml-60 flex-1 p-8">
        <Outlet />
      </main>
    </div>
  )
}
