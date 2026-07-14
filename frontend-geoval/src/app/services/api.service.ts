import { Injectable, inject, signal } from '@angular/core';
import { HttpClient, HttpHeaders } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

@Injectable({
  providedIn: 'root'
})
export class ApiService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = 'http://localhost:8000/api';

  // Signals for state management
  public currentUser = signal<any>(null);
  public token = signal<string | null>(localStorage.getItem('geoval_token'));

  constructor() {
    // If token exists, try to load profile
    if (this.token()) {
      this.getProfile().subscribe({
        next: (res: any) => {
          if (res.success) {
            this.currentUser.set(res.user);
          } else {
            this.logout();
          }
        },
        error: () => this.logout()
      });
    }
  }

  private getHeaders(): HttpHeaders {
    let headers = new HttpHeaders().set('Content-Type', 'application/json');
    if (this.token()) {
      headers = headers.set('Authorization', `Bearer ${this.token()}`);
    }
    return headers;
  }

  // Auth Operations
  login(credentials: any): Observable<any> {
    return this.http.post(`${this.baseUrl}/auth/login`, credentials).pipe(
      tap((res: any) => {
        if (res.success) {
          localStorage.setItem('geoval_token', res.token);
          this.token.set(res.token);
          this.currentUser.set(res.user);
        }
      })
    );
  }

  register(data: any): Observable<any> {
    return this.http.post(`${this.baseUrl}/auth/register`, data).pipe(
      tap((res: any) => {
        if (res.success) {
          localStorage.setItem('geoval_token', res.token);
          this.token.set(res.token);
          this.currentUser.set(res.user);
        }
      })
    );
  }

  getProfile(): Observable<any> {
    return this.http.get(`${this.baseUrl}/auth/me`, { headers: this.getHeaders() });
  }

  logout(): void {
    localStorage.removeItem('geoval_token');
    this.token.set(null);
    this.currentUser.set(null);
  }

  // Parcelles Operations
  getParcelles(): Observable<any[]> {
    return this.http.get<any[]>(`${this.baseUrl}/parcelles`, { headers: this.getHeaders() });
  }

  createParcelle(parcelle: any): Observable<any> {
    return this.http.post(`${this.baseUrl}/parcelles`, parcelle, { headers: this.getHeaders() });
  }

  updateParcelle(id: number, parcelle: any): Observable<any> {
    return this.http.put(`${this.baseUrl}/parcelles/${id}`, parcelle, { headers: this.getHeaders() });
  }

  deleteParcelle(id: number): Observable<any> {
    return this.http.delete(`${this.baseUrl}/parcelles/${id}`, { headers: this.getHeaders() });
  }

  importGeoJson(file: File): Observable<any> {
    const formData = new FormData();
    formData.append('file', file);
    
    // For file upload, don't set Content-Type header manually (browser sets it with boundary)
    let headers = new HttpHeaders();
    if (this.token()) {
      headers = headers.set('Authorization', `Bearer ${this.token()}`);
    }
    return this.http.post(`${this.baseUrl}/parcelles/import-geojson`, formData, { headers });
  }

  // Weather Operations
  getWeather(): Observable<any[]> {
    return this.http.get<any[]>(`${this.baseUrl}/weather`, { headers: this.getHeaders() });
  }

  saveWeather(data: any): Observable<any> {
    return this.http.post(`${this.baseUrl}/weather`, data, { headers: this.getHeaders() });
  }

  // Analyses Operations
  runAnalysis(parcelleId: number, date: string, satelliteImageId?: number): Observable<any> {
    return this.http.post(`${this.baseUrl}/analysis/run`, {
      parcelle_id: parcelleId,
      analysis_date: date,
      satellite_image_id: satelliteImageId
    }, { headers: this.getHeaders() });
  }

  getAnalysisHistory(parcelleId: number): Observable<any[]> {
    return this.http.get<any[]>(`${this.baseUrl}/analysis/history/${parcelleId}`, { headers: this.getHeaders() });
  }

  getGlobalStats(): Observable<any> {
    return this.http.get(`${this.baseUrl}/analysis/global-stats`, { headers: this.getHeaders() });
  }

  // Reports Links
  getExcelReportUrl(): string {
    return `${this.baseUrl}/reports/excel?token=${this.token()}`;
  }

  getPdfReportUrl(): string {
    return `${this.baseUrl}/reports/pdf?token=${this.token()}`;
  }
}
