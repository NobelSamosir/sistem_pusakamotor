import { useState } from 'react'
import api, { errorMessage } from '../lib/api'
import useFetch from '../lib/useFetch'
import { rupiah, tanggal } from '../lib/format'
import MotorFields, { emptyMotor, motorPayload } from '../components/MotorFields'
import PredictionSummary, { Warnings } from '../components/PredictionSummary'
import RangeBar from '../components/RangeBar'
import { Alert, Button, Card, PageHeader, Pagination, Table } from '../components/ui'

export default function Predict() {
  const [motor, setMotor] = useState(emptyMotor)
  const [result, setResult] = useState(null)
  const [error, setError] = useState('')
  const [busy, setBusy] = useState(false)
  const [page, setPage] = useState(1)
  const history = useFetch('/predictions', { page })

  const submit = async (e) => {
    e.preventDefault(); setBusy(true); setError('')
    try {
      const { data } = await api.post('/predictions', motorPayload(motor))
      setResult(data); history.reload()
    } catch (err) { setError(errorMessage(err)) } finally { setBusy(false) }
  }

  return (
    <div>
      <PageHeader title="Prediksi Harga Pasar" subtitle="Range harga dari model XGBoost skenario B: L = prediksi - MAE, U = prediksi + MAE." />
      <Card>
        <Alert>{error}</Alert>
        <form onSubmit={submit}>
          <MotorFields value={motor} onChange={setMotor} />
          <div className="mt-5 flex justify-end"><Button disabled={busy}>{busy ? 'Menghitung...' : 'Prediksi harga'}</Button></div>
        </form>
      </Card>
      {result && (
        <Card title="Hasil prediksi" className="mt-6">
          <Warnings warnings={result.warnings} />
          <PredictionSummary p={result} />
          <RangeBar lower={result.lower_bound} predicted={result.predicted_price} upper={result.upper_bound} />
          <p className="text-xs text-slate-500">Lokasi dan tipe penjual diisi otomatis sesuai showroom (diler, Karawang). Kondisi fisik dan warna tidak dihitung oleh model.</p>
        </Card>
      )}
      <Card title="Riwayat prediksi" className="mt-6">
        <Table
          columns={[
            { key: 'created_at', label: 'Tanggal', render: (r) => tanggal(r.created_at) },
            { key: 'unit', label: 'Motor', render: (r) => `${r.input_snapshot.brand} ${r.input_snapshot.model} ${r.input_snapshot.year}` },
            { key: 'motorcycle', label: 'Unit stok', render: (r) => r.motorcycle?.vehicle_code || '-' },
            { key: 'lower_bound', label: 'L', right: true, render: (r) => rupiah(r.lower_bound) },
            { key: 'predicted_price', label: 'Prediksi', right: true, render: (r) => rupiah(r.predicted_price) },
            { key: 'upper_bound', label: 'U', right: true, render: (r) => rupiah(r.upper_bound) },
          ]}
          rows={history.data?.data}
        />
        <Pagination meta={history.data} onPage={setPage} />
      </Card>
    </div>
  )
}
