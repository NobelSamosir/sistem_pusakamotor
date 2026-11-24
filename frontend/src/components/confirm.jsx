import { createContext, useCallback, useContext, useRef, useState } from 'react'

const Ctx = createContext(null)

/**
 * Popup konfirmasi sebelum menyimpan, mengubah, atau menghapus data.
 * confirm({ title, rows: [[label, nilai]], message, okLabel, danger }) -> Promise<boolean>
 */
export function ConfirmProvider({ children }) {
  const [dlg, setDlg] = useState(null)
  const resolver = useRef(null)

  const confirm = useCallback((opts) => new Promise((resolve) => {
    resolver.current = resolve
    setDlg(opts)
  }), [])

  const close = (val) => { resolver.current?.(val); resolver.current = null; setDlg(null) }

  return (
    <Ctx.Provider value={confirm}>
      {children}
      {dlg && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4" onKeyDown={(e) => e.key === 'Escape' && close(false)}>
          <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
            <h3 className={`text-lg font-semibold ${dlg.danger ? 'text-rose-700' : 'text-slate-900'}`}>{dlg.title}</h3>
            {dlg.message && <p className="mt-2 text-sm text-slate-600">{dlg.message}</p>}
            {dlg.rows?.length > 0 && (
              <dl className="mt-4 divide-y divide-slate-100 rounded-lg bg-slate-50 px-4 text-sm">
                {dlg.rows.filter(([, v]) => v !== undefined && v !== null && v !== '').map(([k, v]) => (
                  <div key={k} className="flex justify-between gap-4 py-2"><dt className="text-slate-500">{k}</dt><dd className="text-right font-medium text-slate-800">{v}</dd></div>
                ))}
              </dl>
            )}
            <div className="mt-6 flex justify-end gap-2">
              <button type="button" className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50" onClick={() => close(false)}>Batal</button>
              <button
                type="button"
                autoFocus
                className={`rounded-lg px-4 py-2 text-sm font-medium text-white ${dlg.danger ? 'bg-rose-600 hover:bg-rose-700' : 'bg-teal-700 hover:bg-teal-800'}`}
                onClick={() => close(true)}
              >
                {dlg.okLabel || 'Ya, simpan'}
              </button>
            </div>
          </div>
        </div>
      )}
    </Ctx.Provider>
  )
}

export const useConfirm = () => useContext(Ctx)

/**
 * Jalankan aksi simpan/hapus setelah dikonfirmasi. Selama proses berjalan, busy = true
 * sehingga tombol terkunci dan klik berulang diabaikan.
 */
export function useSave() {
  const confirm = useConfirm()
  const [busy, setBusy] = useState(false)
  const lock = useRef(false)

  const run = useCallback(async (opts, action) => {
    if (lock.current) return false
    if (opts && !(await confirm(opts))) return false
    lock.current = true; setBusy(true)
    try { await action(); return true } finally { lock.current = false; setBusy(false) }
  }, [confirm])

  return { busy, run }
}

/** Tombol Ubah / Hapus di setiap baris tabel. */
export function RowActions({ onEdit, onDelete, disabled }) {
  return (
    <span className="flex justify-end gap-1">
      {onEdit && <button type="button" disabled={disabled} onClick={onEdit} className="rounded-md px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50 disabled:opacity-40">Ubah</button>}
      {onDelete && <button type="button" disabled={disabled} onClick={onDelete} className="rounded-md px-2 py-1 text-xs font-medium text-rose-600 hover:bg-rose-50 disabled:opacity-40">Hapus</button>}
    </span>
  )
}
