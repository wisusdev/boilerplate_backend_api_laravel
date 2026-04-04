<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Page Builder') }} - {{ $page->title }}</title>

    <!-- GrapesJS CSS -->
    <link rel="stylesheet" href="https://unpkg.com/grapesjs@0.21.10/dist/css/grapes.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        body, html {
            margin: 0;
            height: 100%;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        
        /* Custom top bar */
        #top-bar {
            background: #2c3e50;
            color: white;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 50px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        #top-bar h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 600;
        }
        
        #top-bar-buttons {
            display: flex;
            gap: 10px;
        }
        
        .top-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            color: white;
            transition: all 0.2s;
        }
        
        .btn-back {
            background: #34495e;
        }
        .btn-back:hover {
            background: #465a73;
        }
        
        .btn-save {
            background: #27ae60;
        }
        .btn-save:hover {
            background: #2ecc71;
        }
        
        /* GrapesJS container */
        #gjs {
            height: calc(100vh - 50px);
            overflow: hidden;
        }
        
        /* Toast notifications */
        #toast-container {
            position: fixed;
            top: 70px;
            right: 20px;
            z-index: 9999;
        }
        
        .toast {
            background: #28a745;
            color: white;
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease;
            min-width: 200px;
        }
        
        .toast.error {
            background: #dc3545;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
    </style>
</head>
<body>
    <div id="top-bar">
        <h1>📝 {{ $page->title }} - Page Builder</h1>
        <div id="top-bar-buttons">
            <a href="{{ route('admin.pages.edit', $page) }}" class="top-btn btn-back">← Volver</a>
            <button type="button" id="save-btn" class="top-btn btn-save">💾 Guardar</button>
        </div>
    </div>

    <div id="gjs"></div>
    
    <div id="toast-container"></div>

    <!-- GrapesJS Core -->
    <script src="https://unpkg.com/grapesjs@0.21.10/dist/grapes.min.js"></script>
    <!-- GrapesJS Plugins -->
    <script src="https://unpkg.com/grapesjs-preset-webpage@1.0.3/dist/grapesjs-preset-webpage.min.js"></script>
    <script src="https://unpkg.com/grapesjs-blocks-basic@1.0.2/dist/grapesjs-blocks-basic.min.js"></script>
    <script src="https://unpkg.com/grapesjs-plugin-forms@2.0.6/dist/grapesjs-plugin-forms.min.js"></script>

    <script>
        const pageData = @json($page);
        let editor; // Variable global para el editor
        
        // Primero cargar las imágenes del Media Module
        async function initializeEditor() {
            let mediaAssets = [];
            
            try {
                const response = await fetch('{{ route('admin.media.index') }}', {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                
                if (response.ok) {
                    const data = await response.json();
                    
                    if (data.media && Array.isArray(data.media)) {
                        mediaAssets = data.media
                            .filter(item => item.mime_type && item.mime_type.startsWith('image/'))
                            .map(item => item.url);
                        
                        console.log(`✓ ${mediaAssets.length} imágenes cargadas del Media Module`);
                    }
                }
            } catch (error) {
                console.error('Error al cargar imágenes:', error);
            }
            
            // Ahora inicializar GrapesJS con las imágenes
            editor = grapesjs.init({
                container: '#gjs',
                fromElement: false,
                height: '100%',
                width: 'auto',
                storageManager: false,
                
                // Canvas con Bootstrap
                canvas: {
                    styles: [
                        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css'
                    ],
                    scripts: [
                        'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
                    ]
                },
                
                // Asset Manager - Con imágenes precargadas
                assetManager: {
                    assets: mediaAssets,
                    upload: false,
                    autoAdd: 1
                },
            
            // Plugins
            plugins: [
                'gjs-preset-webpage',
                'gjs-blocks-basic',
                'gjs-plugin-forms'
            ],
            
            pluginsOpts: {
                'gjs-preset-webpage': {
                    blocksBasicOpts: {
                        blocks: ['column1', 'column2', 'column3', 'column3-7', 'text', 'link', 'image', 'video'],
                        flexGrid: true,
                    },
                    blocks: ['link-block', 'quote', 'text-basic'],
                },
                'gjs-blocks-basic': {},
                'gjs-plugin-forms': {
                    blocks: ['form', 'input', 'textarea', 'select', 'button', 'label', 'checkbox', 'radio']
                }
            },
            
            // Bloques personalizados (estilo Elementor)
            blockManager: {
                blocks: [
                    // === BASIC ELEMENTS ===
                    {
                        id: 'heading',
                        label: '<i class="fa fa-heading"></i><div>Heading</div>',
                        category: 'Basic',
                        content: '<h2>Insert your heading text</h2>',
                        attributes: { title: 'Heading element' }
                    },
                    {
                        id: 'paragraph',
                        label: '<i class="fa fa-paragraph"></i><div>Text</div>',
                        category: 'Basic',
                        content: '<p>Insert your text here...</p>',
                        attributes: { title: 'Paragraph element' }
                    },
                    {
                        id: 'button',
                        label: '<i class="fa fa-square"></i><div>Button</div>',
                        category: 'Basic',
                        content: '<a href="#" class="btn btn-primary">Click me</a>',
                        attributes: { title: 'Button' }
                    },
                    {
                        id: 'image',
                        label: '<i class="fa fa-image"></i><div>Image</div>',
                        category: 'Basic',
                        content: { type: 'image' },
                        activate: true,
                        attributes: { title: 'Image' }
                    },
                    {
                        id: 'video',
                        label: '<i class="fa fa-video"></i><div>Video</div>',
                        category: 'Basic',
                        content: { type: 'video' },
                        attributes: { title: 'Video' }
                    },
                    {
                        id: 'divider',
                        label: '<i class="fa fa-minus"></i><div>Divider</div>',
                        category: 'Basic',
                        content: '<hr style="margin: 20px 0; border: 0; border-top: 2px solid #ddd;">',
                        attributes: { title: 'Divider' }
                    },
                    {
                        id: 'spacer',
                        label: '<i class="fa fa-arrows-alt-v"></i><div>Spacer</div>',
                        category: 'Basic',
                        content: '<div style="height: 50px;"></div>',
                        attributes: { title: 'Spacer' }
                    },
                    {
                        id: 'icon',
                        label: '<i class="fa fa-star"></i><div>Icon</div>',
                        category: 'Basic',
                        content: '<i class="fa fa-star fa-3x" style="color: #007bff;"></i>',
                        attributes: { title: 'Icon' }
                    },
                    
                    // === LAYOUT ===
                    {
                        id: 'container',
                        label: '<i class="fa fa-square-full"></i><div>Container</div>',
                        category: 'Layout',
                        content: '<div class="container" style="padding: 20px;"><p>Container content</p></div>',
                        attributes: { title: 'Container' }
                    },
                    {
                        id: 'section',
                        label: '<i class="fa fa-bars"></i><div>Section</div>',
                        category: 'Layout',
                        content: '<section style="padding: 60px 0;"><div class="container"><p>Section content</p></div></section>',
                        attributes: { title: 'Section' }
                    },
                    {
                        id: 'row-2-cols',
                        label: '<i class="fa fa-columns"></i><div>2 Columns</div>',
                        category: 'Layout',
                        content: `
                            <div class="row">
                                <div class="col-md-6"><p>Column 1</p></div>
                                <div class="col-md-6"><p>Column 2</p></div>
                            </div>
                        `
                    },
                    {
                        id: 'row-3-cols',
                        label: '<i class="fa fa-th"></i><div>3 Columns</div>',
                        category: 'Layout',
                        content: `
                            <div class="row">
                                <div class="col-md-4"><p>Column 1</p></div>
                                <div class="col-md-4"><p>Column 2</p></div>
                                <div class="col-md-4"><p>Column 3</p></div>
                            </div>
                        `
                    },
                    {
                        id: 'row-4-cols',
                        label: '<i class="fa fa-th-large"></i><div>4 Columns</div>',
                        category: 'Layout',
                        content: `
                            <div class="row">
                                <div class="col-md-3"><p>Col 1</p></div>
                                <div class="col-md-3"><p>Col 2</p></div>
                                <div class="col-md-3"><p>Col 3</p></div>
                                <div class="col-md-3"><p>Col 4</p></div>
                            </div>
                        `
                    },
                    
                    // === SECTIONS (ELEMENTOR-STYLE) ===
                    {
                        id: 'hero-section',
                        label: '<i class="fa fa-flag"></i><div>Hero</div>',
                        category: 'Sections',
                        content: `
                            <section style="padding: 100px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center;">
                                <div class="container">
                                    <h1 style="font-size: 3.5rem; font-weight: bold; margin-bottom: 1.5rem;">Welcome to Our Website</h1>
                                    <p style="font-size: 1.3rem; margin-bottom: 2rem; opacity: 0.95;">Create amazing experiences with our page builder</p>
                                    <a href="#" class="btn btn-light btn-lg px-5 py-3">Get Started</a>
                                </div>
                            </section>
                        `
                    },
                    {
                        id: 'feature-section',
                        label: '<i class="fa fa-th"></i><div>Features</div>',
                        category: 'Sections',
                        content: `
                            <section style="padding: 80px 20px; background: #f8f9fa;">
                                <div class="container">
                                    <div class="text-center mb-5">
                                        <h2 class="mb-3">Our Features</h2>
                                        <p class="text-muted">Everything you need to succeed</p>
                                    </div>
                                    <div class="row g-4">
                                        <div class="col-md-4">
                                            <div class="card h-100 text-center p-4 border-0 shadow-sm">
                                                <i class="fa fa-rocket fa-4x mb-3 text-primary"></i>
                                                <h3 class="h5">Fast Performance</h3>
                                                <p class="text-muted">Lightning fast load times</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card h-100 text-center p-4 border-0 shadow-sm">
                                                <i class="fa fa-shield-alt fa-4x mb-3 text-success"></i>
                                                <h3 class="h5">Secure</h3>
                                                <p class="text-muted">Bank-level security</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card h-100 text-center p-4 border-0 shadow-sm">
                                                <i class="fa fa-chart-line fa-4x mb-3 text-warning"></i>
                                                <h3 class="h5">Scalable</h3>
                                                <p class="text-muted">Grows with your business</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        `
                    },
                    {
                        id: 'pricing-section',
                        label: '<i class="fa fa-dollar-sign"></i><div>Pricing</div>',
                        category: 'Sections',
                        content: `
                            <section style="padding: 80px 20px;">
                                <div class="container">
                                    <div class="text-center mb-5">
                                        <h2>Choose Your Plan</h2>
                                        <p class="text-muted">Simple, transparent pricing</p>
                                    </div>
                                    <div class="row g-4 justify-content-center">
                                        <div class="col-md-4">
                                            <div class="card text-center shadow-sm">
                                                <div class="card-body p-5">
                                                    <h3 class="card-title">Basic</h3>
                                                    <div class="my-4">
                                                        <span style="font-size: 3rem; font-weight: bold;">$9</span>
                                                        <span class="text-muted">/month</span>
                                                    </div>
                                                    <ul class="list-unstyled mb-4">
                                                        <li class="mb-2">✓ Feature 1</li>
                                                        <li class="mb-2">✓ Feature 2</li>
                                                        <li class="mb-2">✓ Feature 3</li>
                                                    </ul>
                                                    <a href="#" class="btn btn-outline-primary w-100">Choose Plan</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card text-center shadow-lg border-primary">
                                                <div class="card-body p-5">
                                                    <span class="badge bg-primary mb-3">Popular</span>
                                                    <h3 class="card-title">Pro</h3>
                                                    <div class="my-4">
                                                        <span style="font-size: 3rem; font-weight: bold;">$29</span>
                                                        <span class="text-muted">/month</span>
                                                    </div>
                                                    <ul class="list-unstyled mb-4">
                                                        <li class="mb-2">✓ All Basic features</li>
                                                        <li class="mb-2">✓ Feature 4</li>
                                                        <li class="mb-2">✓ Feature 5</li>
                                                    </ul>
                                                    <a href="#" class="btn btn-primary w-100">Choose Plan</a>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card text-center shadow-sm">
                                                <div class="card-body p-5">
                                                    <h3 class="card-title">Enterprise</h3>
                                                    <div class="my-4">
                                                        <span style="font-size: 3rem; font-weight: bold;">$99</span>
                                                        <span class="text-muted">/month</span>
                                                    </div>
                                                    <ul class="list-unstyled mb-4">
                                                        <li class="mb-2">✓ All Pro features</li>
                                                        <li class="mb-2">✓ Priority support</li>
                                                        <li class="mb-2">✓ Custom integration</li>
                                                    </ul>
                                                    <a href="#" class="btn btn-outline-primary w-100">Choose Plan</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        `
                    },
                    {
                        id: 'testimonial-section',
                        label: '<i class="fa fa-quote-left"></i><div>Testimonials</div>',
                        category: 'Sections',
                        content: `
                            <section style="padding: 80px 20px; background: #f8f9fa;">
                                <div class="container">
                                    <div class="text-center mb-5">
                                        <h2>What Our Clients Say</h2>
                                    </div>
                                    <div class="row g-4">
                                        <div class="col-md-4">
                                            <div class="card h-100 border-0 shadow-sm">
                                                <div class="card-body p-4">
                                                    <div class="mb-3">
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                    </div>
                                                    <p class="mb-4">"Outstanding product! It completely transformed our workflow."</p>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-weight: bold;">JD</div>
                                                        <div class="ms-3">
                                                            <strong>John Doe</strong>
                                                            <div class="text-muted small">CEO, Company Inc.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card h-100 border-0 shadow-sm">
                                                <div class="card-body p-4">
                                                    <div class="mb-3">
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                    </div>
                                                    <p class="mb-4">"Best investment we've made this year. Highly recommended!"</p>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-weight: bold;">JS</div>
                                                        <div class="ms-3">
                                                            <strong>Jane Smith</strong>
                                                            <div class="text-muted small">CTO, Tech Corp</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="card h-100 border-0 shadow-sm">
                                                <div class="card-body p-4">
                                                    <div class="mb-3">
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                        <i class="fa fa-star text-warning"></i>
                                                    </div>
                                                    <p class="mb-4">"Game changer! Our productivity increased by 300%."</p>
                                                    <div class="d-flex align-items-center">
                                                        <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; font-weight: bold;">MB</div>
                                                        <div class="ms-3">
                                                            <strong>Mike Brown</strong>
                                                            <div class="text-muted small">Founder, StartupXYZ</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        `
                    },
                    {
                        id: 'cta-section',
                        label: '<i class="fa fa-bullhorn"></i><div>Call to Action</div>',
                        category: 'Sections',
                        content: `
                            <section style="padding: 100px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center;">
                                <div class="container">
                                    <h2 style="font-size: 2.5rem; margin-bottom: 1.5rem;">Ready to Get Started?</h2>
                                    <p style="font-size: 1.2rem; margin-bottom: 2.5rem; opacity: 0.95;">Join thousands of satisfied customers today</p>
                                    <div>
                                        <a href="#" class="btn btn-light btn-lg px-5 py-3 me-3">Sign Up Free</a>
                                        <a href="#" class="btn btn-outline-light btn-lg px-5 py-3">Learn More</a>
                                    </div>
                                </div>
                            </section>
                        `
                    },
                    {
                        id: 'contact-section',
                        label: '<i class="fa fa-envelope"></i><div>Contact</div>',
                        category: 'Sections',
                        content: `
                            <section style="padding: 80px 20px;">
                                <div class="container">
                                    <div class="row">
                                        <div class="col-lg-6 mb-4 mb-lg-0">
                                            <h2 class="mb-4">Get in Touch</h2>
                                            <p class="mb-4">Have a question? We'd love to hear from you. Send us a message and we'll respond as soon as possible.</p>
                                            <div class="mb-3">
                                                <i class="fa fa-map-marker-alt text-primary me-2"></i>
                                                <span>123 Business St, City, State 12345</span>
                                            </div>
                                            <div class="mb-3">
                                                <i class="fa fa-phone text-primary me-2"></i>
                                                <span>+1 (555) 123-4567</span>
                                            </div>
                                            <div class="mb-3">
                                                <i class="fa fa-envelope text-primary me-2"></i>
                                                <span>contact@example.com</span>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <form class="card p-4 shadow-sm border-0">
                                                <div class="mb-3">
                                                    <input type="text" class="form-control" placeholder="Your Name" required>
                                                </div>
                                                <div class="mb-3">
                                                    <input type="email" class="form-control" placeholder="Your Email" required>
                                                </div>
                                                <div class="mb-3">
                                                    <textarea class="form-control" rows="5" placeholder="Your Message" required></textarea>
                                                </div>
                                                <button type="submit" class="btn btn-primary btn-lg w-100">Send Message</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        `
                    },
                    {
                        id: 'team-section',
                        label: '<i class="fa fa-users"></i><div>Team</div>',
                        category: 'Sections',
                        content: `
                            <section style="padding: 80px 20px; background: #f8f9fa;">
                                <div class="container">
                                    <div class="text-center mb-5">
                                        <h2>Meet Our Team</h2>
                                        <p class="text-muted">The people behind our success</p>
                                    </div>
                                    <div class="row g-4">
                                        <div class="col-md-3">
                                            <div class="card border-0 shadow-sm text-center">
                                                <div class="card-body p-4">
                                                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2rem; font-weight: bold;">JD</div>
                                                    <h5>John Doe</h5>
                                                    <p class="text-muted small mb-3">CEO & Founder</p>
                                                    <div>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-twitter"></i></a>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-linkedin"></i></a>
                                                        <a href="#" class="text-primary"><i class="fab fa-github"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card border-0 shadow-sm text-center">
                                                <div class="card-body p-4">
                                                    <div class="rounded-circle bg-success text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2rem; font-weight: bold;">JS</div>
                                                    <h5>Jane Smith</h5>
                                                    <p class="text-muted small mb-3">CTO</p>
                                                    <div>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-twitter"></i></a>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-linkedin"></i></a>
                                                        <a href="#" class="text-primary"><i class="fab fa-github"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card border-0 shadow-sm text-center">
                                                <div class="card-body p-4">
                                                    <div class="rounded-circle bg-info text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2rem; font-weight: bold;">MB</div>
                                                    <h5>Mike Brown</h5>
                                                    <p class="text-muted small mb-3">COO</p>
                                                    <div>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-twitter"></i></a>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-linkedin"></i></a>
                                                        <a href="#" class="text-primary"><i class="fab fa-github"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="card border-0 shadow-sm text-center">
                                                <div class="card-body p-4">
                                                    <div class="rounded-circle bg-warning text-white d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 100px; height: 100px; font-size: 2rem; font-weight: bold;">SD</div>
                                                    <h5>Sarah Davis</h5>
                                                    <p class="text-muted small mb-3">Designer</p>
                                                    <div>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-twitter"></i></a>
                                                        <a href="#" class="text-primary me-2"><i class="fab fa-linkedin"></i></a>
                                                        <a href="#" class="text-primary"><i class="fab fa-github"></i></a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </section>
                        `
                    },
                    
                    // === MEDIA ===
                    {
                        id: 'image-text-box',
                        label: '<i class="fa fa-image"></i><div>Image + Text</div>',
                        category: 'Media',
                        content: `
                            <div class="row align-items-center mb-4">
                                <div class="col-md-6">
                                    <img src="https://via.placeholder.com/600x400" class="img-fluid rounded" alt="Image">
                                </div>
                                <div class="col-md-6">
                                    <h3>Feature Title</h3>
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore.</p>
                                    <a href="#" class="btn btn-primary">Learn More</a>
                                </div>
                            </div>
                        `
                    },
                    {
                        id: 'gallery-grid',
                        label: '<i class="fa fa-th"></i><div>Gallery</div>',
                        category: 'Media',
                        content: `
                            <div class="row g-3">
                                <div class="col-md-4"><img src="https://via.placeholder.com/400x300" class="img-fluid rounded"></div>
                                <div class="col-md-4"><img src="https://via.placeholder.com/400x300" class="img-fluid rounded"></div>
                                <div class="col-md-4"><img src="https://via.placeholder.com/400x300" class="img-fluid rounded"></div>
                                <div class="col-md-4"><img src="https://via.placeholder.com/400x300" class="img-fluid rounded"></div>
                                <div class="col-md-4"><img src="https://via.placeholder.com/400x300" class="img-fluid rounded"></div>
                                <div class="col-md-4"><img src="https://via.placeholder.com/400x300" class="img-fluid rounded"></div>
                            </div>
                        `
                    }
                ]
            }
        });
        
        // Cargar contenido existente
        if (pageData.content_json) {
            try {
                const content = typeof pageData.content_json === 'string' 
                    ? JSON.parse(pageData.content_json) 
                    : pageData.content_json;
                
                if (content.components) {
                    editor.setComponents(content.components);
                }
                if (content.styles) {
                    editor.setStyle(content.styles);
                }
            } catch (e) {
                console.error('Error loading content:', e);
                if (pageData.content_html) {
                    editor.setComponents(pageData.content_html);
                }
            }
        } else if (pageData.content_html) {
            editor.setComponents(pageData.content_html);
        }
        
        // Toast notifications
        function showToast(message, isError = false) {
            const toast = document.createElement('div');
            toast.className = 'toast' + (isError ? ' error' : '');
            toast.textContent = message;
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => toast.remove(), 3000);
        }
        
        // Guardar
        document.getElementById('save-btn').addEventListener('click', async function() {
            if (!editor) {
                showToast('✗ Editor no inicializado', true);
                return;
            }
            
            this.disabled = true;
            this.textContent = '⏳ Guardando...';
            
            try {
                const components = editor.getComponents();
                const styles = editor.getStyle();
                
                const data = {
                    content_html: editor.getHtml(),
                    content_json: {
                        html: editor.getHtml(),
                        css: editor.getCss(),
                        components: JSON.parse(JSON.stringify(components)),
                        styles: JSON.parse(JSON.stringify(styles))
                    },
                    _token: document.querySelector('meta[name="csrf-token"]').content
                };
                
                const response = await fetch('{{ route('admin.pages.builder.save', $page) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': data._token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (response.ok) {
                    showToast('✓ Página guardada exitosamente');
                } else {
                    showToast('✗ ' + (result.message || 'Error al guardar'), true);
                }
            } catch (error) {
                showToast('✗ Error de conexión', true);
                console.error('Save error:', error);
            } finally {
                this.disabled = false;
                this.textContent = '💾 Guardar';
            }
        });
        
        // Auto-save cada 2 minutos
        setInterval(() => {
            const saveBtn = document.getElementById('save-btn');
            if (saveBtn && editor) {
                saveBtn.click();
            }
        }, 120000);
    }
    
    // Inicializar el editor cuando la página cargue
    initializeEditor();
    </script>
</body>
</html>