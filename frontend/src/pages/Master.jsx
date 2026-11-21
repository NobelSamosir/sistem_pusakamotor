import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { Alert, Button, Card, Input, PageHeader, Select, Table, Tabs } from '../components/ui'

function Party({ type }) {
  const isSup = type === 'supplier'
  const url = isSup ? '/suppliers' : '/customers'
  const nameKey = isSup ? 'supplier_name' : 'customer_name'
  const typeKey = isSup ? 'supplier_type' : 'customer_type'
  const empty = { [nameKey]: '', [typeKey]: 'perorangan', phone: '', address: '' }
  const { data, reload } = useFetch(url)
  const [form, setForm] = useState(empty)
  const [editId, setEditId] = useState(null)
  const [error, setError] = useState('')

  const submit = async (e) => {
    e.preventDefault(); setError('')
    try {
      if (editId) await api.put(`${url}/${editId}`, form)
      else await api.post(url, form)
      setForm(empty); setEditId(null); reload()
    } catch (err) { setError(errorMessage(err)) }
  }
  const remove = async (r) => {
    if (!window.confirm(`Hapus ${r[nameKey]}?`)) return
    try { await api.delete(`${url}/${r.id}`); reload() } catch (err) { setError(errorMessage(err)) }
  }

  return (
    <div className="grid gap-6 lg:grid-cols-3">
      <Card title={editId ? 'Ubah data' : isSup ? 'Tambah pemasok' : 'Tambah pelanggan'}>
        <Alert>{error}</Alert>
        <form onSubmit={submit} className="space-y-3">
          <Input label="Nama" value={form[nameKey]} onChange={(e) => setForm({ ...form, [nameKey]: e.target.value })} required />
          <Select label="Jenis" options={[{ value: 'perorangan', label: 'Perorangan' }, { value: 'dealer', label: 'Dealer' }]} value={form[typeKey]} onChange={(e) => setForm({ ...form, [typeKey]: e.target.value })} />
          <Input label="No. HP" value={form.phone || ''} onChange={(e) => setForm({ ...form, phone: e.target.value })} />
          <Input label="Alamat" value={form.address || ''} onChange={(e) => setForm({ ...form, address: e.target.value })} />
          <div className="flex gap-2">
            <Button>{editId ? 'Simpan perubahan' : 'Tambah'}</Button>
            {editId && <Button type="button" variant="secondary" onClick={() => { setForm(empty); setEditId(null) }}>Batal</Button>}
          </div>
        </form>
      </Card>
      <Card className="lg:col-span-2">
        <Table
          columns={[
            { key: nameKey, label: 'Nama' },
            { key: typeKey, label: 'Jenis' },
            { key: 'phone', label: 'No. HP', render: (r) => r.phone || '-' },
            { key: 'aksi', label: '', render: (r) => (
              <div className="flex justify-end gap-1">
                <Button variant="ghost" onClick={() => { setEditId(r.id); setForm({ ...empty, ...r }) }}>Ubah</Button>
                <Button variant="ghost" onClick={() => remove(r)}>Hapus</Button>
              </div>
            ) },
          ]}
          rows={data}
        />
      </Card>
    </div>
  )
}

function Accounts() {
  const { data } = useFetch('/accounts')
  return (
    <Card title="Bagan akun (Tabel 3.8)">
      <Table
        columns={[
          { key: 'account_code', label: 'Kode' },
          { key: 'account_name', label: 'Nama akun' },
          { key: 'account_type', label: 'Jenis' },
          { key: 'normal_balance', label: 'Saldo normal' },
        ]}
        rows={data}
      />
    </Card>
  )
}

export default function Master() {
  const [tab, setTab] = useState('supplier')
  return (
    <div>
      <PageHeader title="Data Master" />
      <Tabs tabs={[{ value: 'supplier', label: 'Pemasok' }, { value: 'customer', label: 'Pelanggan' }, { value: 'account', label: 'Bagan akun' }]} value={tab} onChange={setTab} />
      {tab === 'account' ? <Accounts /> : <Party key={tab} type={tab} />}
    </div>
  )
}
