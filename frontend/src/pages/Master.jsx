import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { Alert, Button, Card, Input, PageHeader, Select, Table, Tabs } from '../components/ui'

function Party({ type }) {
  const isSup = type === 'supplier'
  const url = isSup ? '/suppliers' : '/customers'
  const nameKey = isSup ? 'supplier_name' : 'customer_name'
  const typeKey = isSup ? 'supplier_type' : 'customer_type'
  const { data, reload, error: loadError } = useFetch(url)
  const [form, setForm] = useState(null) // data yang sedang diubah
  const [error, setError] = useState('')

  const submit = async (e) => {
    e.preventDefault(); setError('')
    try {
      await api.put(`${url}/${form.id}`, {
        [nameKey]: form[nameKey], [typeKey]: form[typeKey] || 'perorangan', phone: form.phone || null,
        id_number: form.id_number || null, address: form.address || null,
      })
      setForm(null); reload()
    } catch (err) { setError(errorMessage(err)) }
  }
  const remove = async (r) => {
    if (!window.confirm(`Hapus ${r[nameKey]}?`)) return
    try { await api.delete(`${url}/${r.id}`); reload() } catch (err) { setError(errorMessage(err)) }
  }
  const set = (k) => (e) => setForm({ ...form, [k]: e.target.value })

  return (
    <div className="space-y-6">
      <Alert>{error || loadError}</Alert>
      {form && (
        <Card title={`Ubah data ${form[nameKey]}`}>
          <form onSubmit={submit} className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Input label="Nama" value={form[nameKey]} onChange={set(nameKey)} required />
            <Input label="No. HP" value={form.phone || ''} onChange={set('phone')} />
            <Input label="NIK (nomor KTP)" inputMode="numeric" maxLength={16} pattern="\d{16}" value={form.id_number || ''} onChange={(e) => setForm({ ...form, id_number: e.target.value.replace(/\D/g, '') })} />
            {isSup
              ? <Select label="Jenis penjual" options={[{ value: 'perorangan', label: 'Perorangan' }, { value: 'dealer', label: 'Showroom / dealer lain' }]} value={form[typeKey] || 'perorangan'} onChange={set(typeKey)} />
              : <div />}
            <Input label="Alamat" className="md:col-span-2 xl:col-span-4" value={form.address || ''} onChange={set('address')} />
            <div className="flex gap-2 md:col-span-2 xl:col-span-4">
              <Button>Simpan perubahan</Button>
              <Button type="button" variant="secondary" onClick={() => setForm(null)}>Batal</Button>
            </div>
          </form>
        </Card>
      )}
      <Card>
        <Table
          columns={[
            { key: nameKey, label: 'Nama' },
            ...(isSup ? [{ key: typeKey, label: 'Jenis', render: (r) => (r[typeKey] === 'dealer' ? 'Showroom / dealer' : 'Perorangan') }] : []),
            { key: 'phone', label: 'No. HP', render: (r) => r.phone || '-' },
            { key: 'id_number', label: 'NIK', render: (r) => r.id_number || '-' },
            { key: 'address', label: 'Alamat', render: (r) => r.address || '-' },
            { key: 'aksi', label: '', render: (r) => (
              <div className="flex justify-end gap-1">
                <Button variant="ghost" onClick={() => setForm({ ...r })}>Ubah</Button>
                <Button variant="ghost" onClick={() => remove(r)}>Hapus</Button>
              </div>
            ) },
          ]}
          rows={Array.isArray(data) ? data : []}
          empty={isSup ? 'Belum ada penjual. Data terisi otomatis dari form Pembelian.' : 'Belum ada pembeli. Data terisi otomatis dari form Penjualan dan Uang Muka.'}
        />
      </Card>
    </div>
  )
}

function Accounts() {
  const { data, loading, error, reload } = useFetch('/accounts')
  return (
    <Card title="Bagan akun" actions={<Button variant="secondary" onClick={reload}>Muat ulang</Button>}>
      <Alert>{error}</Alert>
      {loading && <p className="mb-2 text-sm text-slate-400">Memuat...</p>}
      <Table
        columns={[
          { key: 'account_code', label: 'Kode' },
          { key: 'account_name', label: 'Nama akun' },
          { key: 'account_type', label: 'Jenis' },
          { key: 'normal_balance', label: 'Saldo normal' },
        ]}
        rows={Array.isArray(data) ? data : []}
      />
    </Card>
  )
}

export default function Master() {
  const [tab, setTab] = useState('supplier')
  return (
    <div>
      <PageHeader title="Data Master" />
      <Tabs tabs={[{ value: 'supplier', label: 'Penjual' }, { value: 'customer', label: 'Pembeli' }, { value: 'account', label: 'Bagan akun' }]} value={tab} onChange={setTab} />
      {tab === 'account' ? <Accounts /> : <Party key={tab} type={tab} />}
    </div>
  )
}
