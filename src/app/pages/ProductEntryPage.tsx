import React, { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router';
import { toast } from 'sonner';
import { Upload, X, Loader2, Plus, Image as ImageIcon, Pencil } from 'lucide-react';
import { useApp } from '../context/AppContext';
import {
  fetchCategories,
  fetchShops,
  createProduct,
  updateProduct,
  uploadMediaFile,
  fetchMyProducts,
  fetchProductForEdit,
  checkProductEntryPermission,
  mapApiProduct,
  fixImageUrl,
  ApiCategory,
  ApiShop,
  CargoBatch,
  ProductEntryPayload,
} from '../services/api';
import { useApi } from '../hooks/useApi';

interface UploadedMedia {
  id: number;
  filename: string;
  previewUrl: string;
}

export const ProductEntryPage = () => {
  const { authToken, canAddProduct, isAuthenticated, authLoading } = useApp();
  const navigate = useNavigate();
  const { productId: editIdParam } = useParams<{ productId?: string }>();
  const editId = editIdParam ? parseInt(editIdParam) : null;
  const isEditMode = !!editId;

  // Form state
  const [name, setName] = useState('');
  const [nameMn, setNameMn] = useState('');
  const [categoryId, setCategoryId] = useState(0);
  const [shopId, setShopId] = useState(0);
  const [type, setType] = useState<'ready' | 'preorder'>('ready');
  const [price, setPrice] = useState('');
  const [originalPrice, setOriginalPrice] = useState('');
  const [stock, setStock] = useState('');
  const [weightKg, setWeightKg] = useState('');
  const [preorderDate, setPreorderDate] = useState('');
  const [descriptionMn, setDescriptionMn] = useState('');
  const [mainImage, setMainImage] = useState<UploadedMedia | null>(null);
  const [galleryImages, setGalleryImages] = useState<UploadedMedia[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [uploadingMain, setUploadingMain] = useState(false);
  const [uploadingGallery, setUploadingGallery] = useState(false);
  const [loadingProduct, setLoadingProduct] = useState(false);
  const [showInStore, setShowInStore] = useState(true);
  const [orderStatus, setOrderStatus] = useState<'open' | 'closed'>('open');
  const [cargoBatchId, setCargoBatchId] = useState<number | null>(null);
  const [cargoBatches, setCargoBatches] = useState<CargoBatch[]>([]);
  const [hideCargoFee, setHideCargoFee] = useState(false);

  // Data
  const { data: categoriesData } = useApi(() => fetchCategories(), []);
  const { data: shopsData } = useApi(() => fetchShops(), []);
  const { data: myProductsData } = useApi(
    () => (authToken ? fetchMyProducts(authToken) : Promise.resolve([])),
    [authToken]
  );

  // Load open cargo batches
  useEffect(() => {
    if (!authToken) return;
    checkProductEntryPermission(authToken).then((res) => {
      if (res.cargo_batches) setCargoBatches(res.cargo_batches);
    });
  }, [authToken]);

  // Redirect if not authorized
  useEffect(() => {
    if (!authLoading && (!isAuthenticated || !canAddProduct)) {
      navigate('/', { replace: true });
    }
  }, [authLoading, isAuthenticated, canAddProduct, navigate]);

  // Load product data for edit mode
  useEffect(() => {
    if (!editId || !authToken) return;
    setLoadingProduct(true);
    fetchProductForEdit(authToken, editId)
      .then((p) => {
        setName(p.name ?? '');
        setNameMn(p.name_mn);
        setCategoryId(p.category_id);
        setShopId(p.shop_id);
        setType(p.type);
        setPrice(p.price.toString());
        setOriginalPrice(p.original_price ? p.original_price.toString() : '');
        setStock(p.stock !== null && p.stock !== undefined ? p.stock.toString() : '');
        setWeightKg(p.weight_kg ? p.weight_kg.toString() : '');
        setPreorderDate(p.preorder_date ?? '');
        setDescriptionMn(p.description_mn ?? '');
        setShowInStore(p.show_in_store !== 0);
        setOrderStatus(p.order_status ?? 'open');
        setCargoBatchId(p.cargo_batch_id ?? null);
        setHideCargoFee(!!p.hide_cargo_fee);
        if (p.main_image) {
          setMainImage({
            id: p.main_image.id,
            filename: p.main_image.filename,
            previewUrl: `${import.meta.env.BASE_URL}backend/uploads/media/${p.main_image.filename}`,
          });
        }
        if (p.gallery_images?.length) {
          setGalleryImages(
            p.gallery_images.map((g) => ({
              id: g.id,
              filename: g.filename,
              previewUrl: `${import.meta.env.BASE_URL}backend/uploads/media/${g.filename}`,
            }))
          );
        }
      })
      .catch((err) => {
        toast.error(err instanceof Error ? err.message : 'Бараа ачаалахад алдаа гарлаа');
        navigate('/product-entry', { replace: true });
      })
      .finally(() => setLoadingProduct(false));
  }, [editId, authToken]);

  if (authLoading || loadingProduct) {
    return (
      <div className="flex justify-center items-center py-20">
        <Loader2 className="w-8 h-8 animate-spin text-blue-600" />
      </div>
    );
  }

  if (!isAuthenticated || !canAddProduct) return null;

  const handleMainImageUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file || !authToken) return;
    setUploadingMain(true);
    try {
      const result = await uploadMediaFile(authToken, file);
      setMainImage({
        id: result.media.id,
        filename: result.media.filename,
        previewUrl: `${import.meta.env.BASE_URL}backend/uploads/media/${result.media.filename}`,
      });
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Зураг оруулахад алдаа гарлаа');
    } finally {
      setUploadingMain(false);
      e.target.value = '';
    }
  };

  const handleGalleryUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || !authToken) return;
    setUploadingGallery(true);
    try {
      for (const file of Array.from(files)) {
        const result = await uploadMediaFile(authToken, file);
        setGalleryImages((prev) => [
          ...prev,
          {
            id: result.media.id,
            filename: result.media.filename,
            previewUrl: `${import.meta.env.BASE_URL}backend/uploads/media/${result.media.filename}`,
          },
        ]);
      }
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Зураг оруулахад алдаа гарлаа');
    } finally {
      setUploadingGallery(false);
      e.target.value = '';
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!authToken) return;

    if (!nameMn.trim()) { toast.error('Барааны нэр оруулна уу'); return; }
    if (!categoryId) { toast.error('Ангилал сонгоно уу'); return; }
    if (!shopId) { toast.error('Дэлгүүр сонгоно уу'); return; }
    if (!price || parseFloat(price) <= 0) { toast.error('Үнэ оруулна уу'); return; }
    if (type === 'preorder' && (!weightKg || parseFloat(weightKg) <= 0)) {
      toast.error('Урьдчилсан захиалгын барааны жин оруулна уу');
      return;
    }

    setSubmitting(true);
    try {
      const payload: ProductEntryPayload = {
        name: name.trim() || nameMn.trim(),
        name_mn: nameMn.trim(),
        category_id: categoryId,
        shop_id: shopId,
        type,
        price: parseFloat(price),
      };

      if (originalPrice) payload.original_price = parseFloat(originalPrice);
      if (stock) payload.stock = parseInt(stock);
      if (weightKg) payload.weight_kg = parseFloat(weightKg);
      if (preorderDate) payload.preorder_date = preorderDate;
      if (descriptionMn.trim()) payload.description_mn = descriptionMn.trim();
      if (mainImage) payload.main_image_id = mainImage.id;
      if (galleryImages.length > 0) payload.image_ids = galleryImages.map((g) => g.id);
      payload.show_in_store = showInStore ? 1 : 0;
      payload.order_status = orderStatus;
      payload.cargo_batch_id = cargoBatchId;
      payload.hide_cargo_fee = hideCargoFee ? 1 : 0;

      if (isEditMode && editId) {
        const result = await updateProduct(authToken, editId, payload);
        toast.success('Бараа амжилттай шинэчлэгдлээ!');
        navigate(`/product/${result.slug}`, { replace: true });
      } else {
        const result = await createProduct(authToken, payload);
        toast.success('Бараа амжилттай нэмэгдлээ!');
        navigate(`/product/${result.slug}`, { replace: true });
      }
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Бараа хадгалахад алдаа гарлаа');
    } finally {
      setSubmitting(false);
    }
  };

  const myProducts = myProductsData?.map(mapApiProduct) ?? [];

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <h1 className="text-2xl font-bold text-gray-900 mb-2">{isEditMode ? 'Бараа засах' : 'Бараа нэмэх'}</h1>
      <p className="text-gray-500 text-sm mb-8">{isEditMode ? 'Барааны мэдээллийг шинэчлэх' : 'Шинэ бараа оруулах маягт'}</p>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Product Name */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-5">
          <h2 className="text-lg font-semibold text-gray-800">Ерөнхий мэдээлэл</h2>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Барааны нэр *</label>
              <input
                type="text"
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="Жишээ: Electric vacuum cleaner"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Барааны нэр (монгол) *</label>
              <input
                type="text"
                value={nameMn}
                onChange={(e) => setNameMn(e.target.value)}
                placeholder="Жишээ: Цахилгаан тоос сорогч"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Ангилал *</label>
              <select
                value={categoryId}
                onChange={(e) => setCategoryId(Number(e.target.value))}
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              >
                <option value={0}>Сонгох...</option>
                {categoriesData?.map((cat: ApiCategory) => (
                  <option key={cat.id} value={cat.id}>{cat.name_mn || cat.name}</option>
                ))}
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Дэлгүүр *</label>
              <select
                value={shopId}
                onChange={(e) => setShopId(Number(e.target.value))}
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              >
                <option value={0}>Сонгох...</option>
                {shopsData?.map((shop: ApiShop) => (
                  <option key={shop.id} value={shop.id}>{shop.name_mn || shop.name}</option>
                ))}
              </select>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-1">Төрөл *</label>
            <div className="flex gap-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="type" value="ready" checked={type === 'ready'} onChange={() => setType('ready')} className="text-blue-600" />
                <span className="text-sm text-gray-700">Бэлэн бараа</span>
              </label>
              <label className="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="type" value="preorder" checked={type === 'preorder'} onChange={() => setType('preorder')} className="text-blue-600" />
                <span className="text-sm text-gray-700">Урьдчилсан захиалга</span>
              </label>
            </div>
          </div>
        </div>

        {/* Pricing & Stock */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-5">
          <h2 className="text-lg font-semibold text-gray-800">Үнэ, нөөц</h2>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Үнэ (₮) *</label>
              <input
                type="number"
                value={price}
                onChange={(e) => setPrice(e.target.value)}
                placeholder="0"
                min="1"
                step="1"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Хуучин үнэ (₮)</label>
              <input
                type="number"
                value={originalPrice}
                onChange={(e) => setOriginalPrice(e.target.value)}
                placeholder="Хямдрал байвал"
                min="0"
                step="1"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          </div>

          {type === 'ready' && (
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Нөөцийн тоо</label>
              <input
                type="number"
                value={stock}
                onChange={(e) => setStock(e.target.value)}
                placeholder="0"
                min="0"
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
          )}

          {type === 'preorder' && (
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Жин (кг) *</label>
                <input
                  type="number"
                  value={weightKg}
                  onChange={(e) => setWeightKg(e.target.value)}
                  placeholder="0.000"
                  min="0.001"
                  step="0.001"
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                  required
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Ирэх хугацаа</label>
                <input
                  type="date"
                  value={preorderDate}
                  onChange={(e) => setPreorderDate(e.target.value)}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                />
              </div>
            </div>
          )}
        </div>

        {/* Images */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-5">
          <h2 className="text-lg font-semibold text-gray-800">Зураг</h2>

          {/* Main Image */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Үндсэн зураг</label>
            {mainImage ? (
              <div className="relative inline-block">
                <img src={mainImage.previewUrl} alt="Main" className="w-32 h-32 object-cover rounded-lg border" />
                <button
                  type="button"
                  onClick={() => setMainImage(null)}
                  className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600"
                >
                  <X className="w-3 h-3" />
                </button>
              </div>
            ) : (
              <label className="flex flex-col items-center justify-center w-32 h-32 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 transition-colors">
                {uploadingMain ? (
                  <Loader2 className="w-6 h-6 animate-spin text-blue-500" />
                ) : (
                  <>
                    <ImageIcon className="w-8 h-8 text-gray-400" />
                    <span className="text-xs text-gray-500 mt-1">Зураг сонгох</span>
                  </>
                )}
                <input type="file" className="hidden" accept="image/*" onChange={handleMainImageUpload} disabled={uploadingMain} />
              </label>
            )}
          </div>

          {/* Gallery */}
          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Нэмэлт зурагнууд</label>
            <div className="flex flex-wrap gap-3">
              {galleryImages.map((img, idx) => (
                <div key={img.id} className="relative">
                  <img src={img.previewUrl} alt={`Gallery ${idx + 1}`} className="w-24 h-24 object-cover rounded-lg border" />
                  <button
                    type="button"
                    onClick={() => setGalleryImages((prev) => prev.filter((g) => g.id !== img.id))}
                    className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600"
                  >
                    <X className="w-3 h-3" />
                  </button>
                </div>
              ))}
              <label className="flex flex-col items-center justify-center w-24 h-24 border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 transition-colors">
                {uploadingGallery ? (
                  <Loader2 className="w-5 h-5 animate-spin text-blue-500" />
                ) : (
                  <>
                    <Plus className="w-6 h-6 text-gray-400" />
                    <span className="text-[10px] text-gray-500 mt-0.5">Нэмэх</span>
                  </>
                )}
                <input type="file" className="hidden" accept="image/*" multiple onChange={handleGalleryUpload} disabled={uploadingGallery} />
              </label>
            </div>
          </div>
        </div>

        {/* Description */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-5">
          <h2 className="text-lg font-semibold text-gray-800">Тайлбар</h2>
          <textarea
            value={descriptionMn}
            onChange={(e) => setDescriptionMn(e.target.value)}
            placeholder="Барааны дэлгэрэнгүй тайлбар..."
            rows={4}
            className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-y"
          />
        </div>

        {/* Settings: show_in_store, order_status, cargo_batch_id */}
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 space-y-5">
          <h2 className="text-lg font-semibold text-gray-800">Тохиргоо</h2>

          <div className="flex items-center gap-3">
            <label className="relative inline-flex items-center cursor-pointer">
              <input
                type="checkbox"
                checked={showInStore}
                onChange={(e) => setShowInStore(e.target.checked)}
                className="sr-only peer"
              />
              <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600" />
            </label>
            <span className="text-sm font-medium text-gray-700">Дэлгүүрт харуулах</span>
          </div>

          {type === 'preorder' && (
            <div className="flex items-center gap-3">
              <label className="relative inline-flex items-center cursor-pointer">
                <input
                  type="checkbox"
                  checked={hideCargoFee}
                  onChange={(e) => setHideCargoFee(e.target.checked)}
                  className="sr-only peer"
                />
                <div className="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-orange-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-orange-500" />
              </label>
              <div>
                <span className="text-sm font-medium text-gray-700">Ачааны төлбөр нуух</span>
                <p className="text-xs text-gray-500">Идэвхтэй бол ачаа ирэхэд төлнө гэж харуулна</p>
              </div>
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">Захиалгын төлөв</label>
              <select
                value={orderStatus}
                onChange={(e) => setOrderStatus(e.target.value as 'open' | 'closed')}
                className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="open">Нээлттэй</option>
                <option value="closed">Хаалттай</option>
              </select>
            </div>

            {cargoBatches.length > 0 && (
              <div>
                <label className="block text-sm font-medium text-gray-700 mb-1">Ачааны багц</label>
                <select
                  value={cargoBatchId ?? ''}
                  onChange={(e) => setCargoBatchId(e.target.value ? Number(e.target.value) : null)}
                  className="w-full px-3 py-2.5 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                >
                  <option value="">Сонгох шаардлагагүй</option>
                  {cargoBatches.map((b) => (
                    <option key={b.id} value={b.id}>{b.name} ({b.cargo_rate_per_kg.toLocaleString()}₮/кг)</option>
                  ))}
                </select>
              </div>
            )}
          </div>
        </div>

        {/* Submit */}
        <div className="flex justify-end gap-3">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="px-6 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors"
          >
            Болих
          </button>
          <button
            type="submit"
            disabled={submitting}
            className="px-6 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2"
          >
            {submitting ? (
              <>
                <Loader2 className="w-4 h-4 animate-spin" />
                Хадгалж байна...
              </>
            ) : (
              <>
                <Upload className="w-4 h-4" />
                {isEditMode ? 'Хадгалах' : 'Бараа нэмэх'}
              </>
            )}
          </button>
        </div>
      </form>

      {/* My Products List */}
      {myProducts.length > 0 && (
        <div className="mt-12">
          <h2 className="text-lg font-semibold text-gray-800 mb-4">Миний оруулсан бараа ({myProducts.length})</h2>
          <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
            {myProducts.map((p) => (
              <div
                key={p.id}
                className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow"
              >
                <button
                  onClick={() => navigate(`/product/${p.slug}`)}
                  className="w-full text-left"
                >
                  <div className="aspect-square bg-gray-100">
                    {p.image ? (
                      <img src={p.image} alt={p.nameMn} className="w-full h-full object-cover" />
                    ) : (
                      <div className="w-full h-full flex items-center justify-center">
                        <ImageIcon className="w-8 h-8 text-gray-300" />
                      </div>
                    )}
                  </div>
                  <div className="p-3">
                    <p className="text-sm font-medium text-gray-900 line-clamp-2">{p.nameMn}</p>
                    <p className="text-sm font-bold text-blue-600 mt-1">{p.price.toLocaleString()}₮</p>
                  </div>
                </button>
                <div className="px-3 pb-3">
                  <button
                    onClick={() => navigate(`/product-entry/${p.id}`)}
                    className="w-full flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded-lg hover:bg-blue-100 transition-colors"
                  >
                    <Pencil className="w-3 h-3" />
                    Засах
                  </button>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
};
