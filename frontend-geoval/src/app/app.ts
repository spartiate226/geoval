import { Component, signal, effect, inject, AfterViewInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { ApiService } from './services/api.service';
import * as L from 'leaflet';

@Component({
  selector: 'app-root',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './app.html',
  styleUrl: './app.css'
})
export class App implements AfterViewInit {
  public readonly api = inject(ApiService);

  // States
  public parcelles = signal<any[]>([]);
  public selectedParcelle = signal<any>(null);
  public analysisHistory = signal<any[]>([]);
  public globalStats = signal<any>({
    total_parcelles: 0,
    average_yield: 0,
    average_ndvi: 0.55,
    average_water_productivity: 1.25
  });

  // UI States
  public activeTab = signal<'map' | 'dashboard' | 'admin'>('map');
  public isDrawing = signal<boolean>(false);
  public showImportModal = signal<boolean>(false);
  public showAddParcelModal = signal<boolean>(false);
  public showWeatherModal = signal<boolean>(false);
  public currentRole = signal<'admin' | 'analyste' | 'chercheur'>('analyste'); // default evaluation role
  public analysisDate = signal<string>(new Date().toISOString().split('T')[0]);
  public isCalculating = signal<boolean>(false);

  // New Parcel Form
  public newParcel = {
    name: '',
    owner: '',
    crop_type: 'Riz',
    planting_date: '',
    yield: 0,
    geometry: {
      type: 'Polygon',
      coordinates: [] as any[]
    }
  };

  // Weather Form
  public newWeather = {
    date: new Date().toISOString().split('T')[0],
    temperature: 31.5,
    humidity: 55,
    precipitation: 0.0,
    wind_speed: 3.2,
    solar_radiation: 245.0
  };

  // Leaflet Map Reference
  private map!: L.Map;
  private geojsonLayer!: L.GeoJSON;
  private drawnItems = new L.FeatureGroup();

  constructor() {
    // Reload parcelles on start
    this.loadParcelles();
    this.loadGlobalStats();

    // Effect to update map when parcelles change
    effect(() => {
      const data = this.parcelles();
      if (this.map && data.length > 0) {
        this.renderParcellesOnMap(data);
      }
    });
  }

  ngAfterViewInit(): void {
    this.initMap();
  }

  private initMap(): void {
    // Center of Bagré Aval irrigation perimeter
    const bagreCoords: L.LatLngExpression = [11.48, -0.42];
    
    this.map = L.map('map-container', {
      center: bagreCoords,
      zoom: 13,
      zoomControl: true
    });

    // Satellite Imagery Layer
    const satelliteLayer = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
      attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    });

    // OpenStreetMap Layer
    const streetLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; OpenStreetMap contributors'
    });

    satelliteLayer.addTo(this.map);

    // Layer control
    const baseLayers = {
      "Satellite": satelliteLayer,
      "Plan de rue": streetLayer
    };

    L.control.layers(baseLayers).addTo(this.map);
    this.map.addLayer(this.drawnItems);

    // Render initial parcelles if loaded
    if (this.parcelles().length > 0) {
      this.renderParcellesOnMap(this.parcelles());
    }
  }

  private loadParcelles(): void {
    this.api.getParcelles().subscribe({
      next: (data) => this.parcelles.set(data),
      error: () => {
        // Fallback demo data if backend connection or PostGIS is not yet fully migrated
        const demoData = [
          {
            id: 1,
            name: "Périmètre Rizicole A1",
            owner: "Coopérative Bagré Nord",
            crop_type: "Riz",
            planting_date: "2026-03-15",
            yield: 5.8,
            geojson: '{"type":"Polygon","coordinates":[[[-0.43,11.49],[-0.41,11.49],[-0.41,11.47],[-0.43,11.47],[-0.43,11.49]]]}'
          },
          {
            id: 2,
            name: "Parcelle Maïs B4",
            owner: "Diallo Oumarou",
            crop_type: "Maïs",
            planting_date: "2026-04-10",
            yield: 4.2,
            geojson: '{"type":"Polygon","coordinates":[[[-0.45,11.48],[-0.44,11.48],[-0.44,11.46],[-0.45,11.46],[-0.45,11.48]]]}'
          }
        ];
        this.parcelles.set(demoData);
      }
    });
  }

  private loadGlobalStats(): void {
    this.api.getGlobalStats().subscribe({
      next: (stats) => this.globalStats.set(stats),
      error: () => {}
    });
  }

  private renderParcellesOnMap(parcellesData: any[]): void {
    if (this.geojsonLayer) {
      this.map.removeLayer(this.geojsonLayer);
    }

    const features = parcellesData.map(p => {
      const geom = typeof p.geojson === 'string' ? JSON.parse(p.geojson) : p.geojson;
      return {
        type: 'Feature',
        properties: { id: p.id, name: p.name, crop_type: p.crop_type, yield: p.yield },
        geometry: geom
      };
    });

    const geojsonData: any = {
      type: 'FeatureCollection',
      features: features.filter(f => f.geometry)
    };

    this.geojsonLayer = L.geoJSON(geojsonData, {
      style: (feature) => {
        // Color based on crop type
        const crop = feature?.properties?.crop_type?.toLowerCase() || '';
        let color = '#3b82f6'; // Blue
        if (crop.includes('riz') || crop.includes('rice')) {
          color = '#10b981'; // Green
        } else if (crop.includes('maïs') || crop.includes('mais')) {
          color = '#f59e0b'; // Amber
        }
        
        return {
          color: color,
          weight: 2,
          fillColor: color,
          fillOpacity: 0.35
        };
      },
      onEachFeature: (feature, layer) => {
        const id = feature.properties.id;
        const name = feature.properties.name;
        const crop = feature.properties.crop_type;
        
        layer.bindTooltip(`<strong>${name}</strong><br>Culture: ${crop}`, { sticky: true });
        
        layer.on('click', () => {
          this.selectParcelleById(id);
        });
      }
    }).addTo(this.map);

    // Fit map bounds
    try {
      const bounds = this.geojsonLayer.getBounds();
      if (bounds.isValid()) {
        this.map.fitBounds(bounds, { padding: [50, 50] });
      }
    } catch (e) {}
  }

  public selectParcelleById(id: number): void {
    const p = this.parcelles().find(item => item.id === id);
    if (p) {
      this.selectedParcelle.set(p);
      this.loadAnalysisHistory(id);
      
      // Highlight on map
      this.geojsonLayer.eachLayer((layer: any) => {
        if (layer.feature.properties.id === id) {
          layer.setStyle({ fillOpacity: 0.65, weight: 4, color: '#f43f5e' });
        } else {
          // Reset style
          const crop = layer.feature.properties.crop_type?.toLowerCase() || '';
          let color = '#3b82f6';
          if (crop.includes('riz') || crop.includes('rice')) color = '#10b981';
          else if (crop.includes('maïs') || crop.includes('mais')) color = '#f59e0b';
          layer.setStyle({ color: color, weight: 2, fillOpacity: 0.35 });
        }
      });
    }
  }

  private loadAnalysisHistory(parcelleId: number): void {
    this.api.getAnalysisHistory(parcelleId).subscribe({
      next: (history) => {
        this.analysisHistory.set(history);
      },
      error: () => {
        // Fallback mock history for visual simulation
        const mockHistory = [
          {
            id: 101,
            analysis_date: '2026-05-01',
            ndvi_min: 0.2100,
            ndvi_max: 0.4500,
            ndvi_mean: 0.3300,
            et0: 5.12,
            etc: 2.56,
            water_productivity: 0.85,
            interpretation: 'Couverture végétale en phase initiale. Consommation hydrique modérée.',
            recommendations: 'Maintenir les apports d\'eau réguliers.'
          },
          {
            id: 102,
            analysis_date: '2026-05-15',
            ndvi_min: 0.4500,
            ndvi_max: 0.8200,
            ndvi_mean: 0.6800,
            et0: 5.45,
            etc: 6.54,
            water_productivity: 1.15,
            interpretation: 'Forte vigueur végétative en pleine croissance. Pic de transpiration.',
            recommendations: 'Augmenter le débit d\'irrigation pour combler l\'ETc élevée.'
          },
          {
            id: 103,
            analysis_date: '2026-06-01',
            ndvi_min: 0.5200,
            ndvi_max: 0.8900,
            ndvi_mean: 0.7600,
            et0: 5.60,
            etc: 6.72,
            water_productivity: 1.34,
            interpretation: 'Maturation de la biomasse végétale. Excellente santé globale.',
            recommendations: 'Irrigation stable conseillée.'
          }
        ];
        this.analysisHistory.set(mockHistory);
      }
    });
  }

  public runNewAnalysis(): void {
    const selected = this.selectedParcelle();
    if (!selected) return;

    this.isCalculating.set(true);
    
    this.api.runAnalysis(selected.id, this.analysisDate()).subscribe({
      next: (res: any) => {
        this.isCalculating.set(false);
        if (res.success) {
          this.loadAnalysisHistory(selected.id);
          this.loadGlobalStats();
        }
      },
      error: () => {
        // Mock offline analysis trigger
        setTimeout(() => {
          this.isCalculating.set(false);
          const newAnal = {
            id: Math.floor(Math.random() * 1000),
            analysis_date: this.analysisDate(),
            ndvi_min: 0.5800,
            ndvi_max: 0.9100,
            ndvi_mean: 0.7900,
            et0: 5.75,
            etc: 6.90,
            water_productivity: 1.42,
            interpretation: '(DÉMO HORS-LIGNE) Indice de végétation maximal observé. Couverture chlorophyllienne optimale.',
            recommendations: 'Moduler l\'irrigation. Préparer l\'assèchement progressif avant la récolte.'
          };
          this.analysisHistory.set([...this.analysisHistory(), newAnal]);
        }, 1500);
      }
    });
  }

  // Import operations
  public onGeoJsonFileSelected(event: any): void {
    const file: File = event.target.files[0];
    if (file) {
      this.api.importGeoJson(file).subscribe({
        next: (res: any) => {
          if (res.success) {
            this.parcelles.set(res.parcelles);
            this.showImportModal.set(false);
            alert("Importation réussie !");
          }
        },
        error: (err) => {
          // Client-side parser fallback for visual demonstration if backend fails
          const reader = new FileReader();
          reader.onload = (e: any) => {
            try {
              const geojson = JSON.parse(e.target.result);
              let features = geojson.features || (geojson.type === 'Feature' ? [geojson] : []);
              
              const imported = features.map((f: any, idx: number) => ({
                id: 1000 + idx,
                name: f.properties?.name || `Import ${idx + 1}`,
                owner: f.properties?.owner || 'Importé',
                crop_type: f.properties?.crop_type || 'Riz',
                planting_date: f.properties?.planting_date || '2026-04-01',
                yield: f.properties?.yield || 4.5,
                geojson: JSON.stringify(f.geometry)
              }));
              
              this.parcelles.set([...this.parcelles(), ...imported]);
              this.showImportModal.set(false);
              alert("(Démo) " + imported.length + " parcelles importées localement.");
            } catch (e) {
              alert("Erreur de décodage du fichier.");
            }
          };
          reader.readAsText(file);
        }
      });
    }
  }

  // Add Manual Parcel
  public startDrawingParcel(): void {
    this.isDrawing.set(true);
    alert("Cliquez sur la carte pour tracer votre polygone. Double-cliquez pour terminer la saisie.");
    
    // Setup drawing handler on Leaflet map
    const points: L.LatLng[] = [];
    const tempPolyline = L.polyline([], { color: '#f43f5e', dashArray: '5, 5' }).addTo(this.map);
    const tempPolygon = L.polygon([], { color: '#f43f5e', fillColor: '#f43f5e', fillOpacity: 0.2 }).addTo(this.map);

    const onClick = (e: L.LeafletMouseEvent) => {
      points.push(e.latlng);
      tempPolyline.setLatLngs(points);
      tempPolygon.setLatLngs(points);
    };

    const onDblClick = () => {
      this.map.off('click', onClick);
      this.map.off('dblclick', onDblClick);
      
      this.map.removeLayer(tempPolyline);
      this.map.removeLayer(tempPolygon);
      
      if (points.length >= 3) {
        // Convert to geojson format (closed loop)
        const coords = points.map(p => [p.lng, p.lat]);
        coords.push(coords[0]); // close
        
        this.newParcel.geometry.coordinates = [coords];
        this.isDrawing.set(false);
        this.showAddParcelModal.set(true);
      } else {
        alert("Un polygone requiert au moins 3 points.");
        this.isDrawing.set(false);
      }
    };

    this.map.on('click', onClick);
    this.map.on('dblclick', onDblClick);
  }

  public saveManualParcel(): void {
    this.api.createParcelle(this.newParcel).subscribe({
      next: (res: any) => {
        if (res.success) {
          this.loadParcelles();
          this.showAddParcelModal.set(false);
          this.resetParcelForm();
        }
      },
      error: () => {
        // Local simulation fallback
        const mockNew = {
          id: Math.floor(Math.random() * 1000) + 100,
          name: this.newParcel.name || 'Nouvelle Parcelle',
          owner: this.newParcel.owner || 'Exploitant local',
          crop_type: this.newParcel.crop_type,
          planting_date: this.newParcel.planting_date,
          yield: this.newParcel.yield,
          geojson: JSON.stringify(this.newParcel.geometry)
        };
        this.parcelles.set([...this.parcelles(), mockNew]);
        this.showAddParcelModal.set(false);
        this.resetParcelForm();
      }
    });
  }

  private resetParcelForm(): void {
    this.newParcel = {
      name: '',
      owner: '',
      crop_type: 'Riz',
      planting_date: '',
      yield: 0,
      geometry: { type: 'Polygon', coordinates: [] }
    };
  }

  // Weather record
  public saveWeatherData(): void {
    this.api.saveWeather(this.newWeather).subscribe({
      next: () => {
        this.showWeatherModal.set(false);
        alert("Données météo enregistrées avec succès !");
      },
      error: () => {
        this.showWeatherModal.set(false);
        alert("(Démo) Données enregistrées localement.");
      }
    });
  }

  // Role management helper
  public setRole(role: 'admin' | 'analyste' | 'chercheur'): void {
    this.currentRole.set(role);
  }

  // Reports
  public downloadExcel(): void {
    window.open(this.api.getExcelReportUrl(), '_blank');
  }

  public printPdf(): void {
    window.open(this.api.getPdfReportUrl(), '_blank');
  }
}
